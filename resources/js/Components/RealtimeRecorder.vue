<script lang="ts" setup>
import axios from 'axios';
import { computed, onBeforeUnmount, ref, shallowRef, reactive, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

type StructuredEpisode = {
    start_time?: string | null;
    end_time?: string | null;
    intensity?: number | null;
    pain_location?: string | null;
    aura?: boolean | null;
    symptoms?: string[] | null;
    triggers?: string[] | null;
    what_you_tried?: string | null;
    notes?: string | null;
    confidence_breakdown?: Record<string, number>;
};

type UploadResponse = {
    id: number;
    status: 'queued';
};

const emits = defineEmits<{
    recorded: [UploadResponse];
    error: [string];
    'conversation-complete': [{
        episodeId: number | null;
        structured: StructuredEpisode | null;
        transcript: string;
        message?: string;
    }];
}>();

// --- Constants ---
const REALTIME_MODEL = 'gpt-4o-mini-realtime-preview-2024-12-17';
const SAMPLE_RATE = 24000; // OpenAI Realtime requires 24kHz

// --- State ---
const isRecording = ref(false);
const isConnected = ref(false);
const statusMessage = ref('Ready to record');
const isSavingEpisode = ref(false);
const transcriptHistory = ref('');
const displayTranscript = computed(() => transcriptHistory.value || 'Transcription will appear here...');

// Template Compatibility
const conversationActive = computed(() => isRecording.value);
const realtimeStatus = computed(() => statusMessage.value);
const isSpeaking = ref(false); // Placeholder
const isRealtimeConnected = computed(() => isConnected.value);
const speechFallbackActive = ref(false); // No fallback in this version

const canStart = computed(() => !isRecording.value && !isSavingEpisode.value);
const realtimeError = ref<string | null>(null);
const hasSavedEpisode = computed(() => isSavingEpisode.value);
const errorMessage = ref<string | null>(null);
const page = usePage<{ auth: { user: any | null } }>();

// Refs for cleanup
const audioContext = shallowRef<AudioContext | null>(null);
const mediaStream = shallowRef<MediaStream | null>(null);
const ws = shallowRef<WebSocket | null>(null);
const audioProcessor = shallowRef<ScriptProcessorNode | null>(null);
const inputSource = shallowRef<MediaStreamAudioSourceNode | null>(null);

// Audio Playback Queue
let nextAudioStartTime = 0;

// --- Lifecycle ---
onMounted(() => {
    console.log('AudioRecorder mounted (Version: Cleaned-Realtime-Only)');
});

onBeforeUnmount(() => {
    cleanupSession();
});

// --- Main Actions ---

async function startConversation() {
    // 1. Ensure clean slate
    await cleanupSession();

    try {
        isRecording.value = true;
        statusMessage.value = 'Connecting...';

        // 2. Fetch Ephemeral Token
        const { data: sessionData } = await axios.get('/voice/session');
        const clientSecret = sessionData.client_secret?.value;
        const ephemeralId = sessionData.id;

        if (!clientSecret) throw new Error('Failed to obtain session token.');

        // 3. Initialize Audio Context
        const AudioContextCtor = window.AudioContext || (window as any).webkitAudioContext;
        const ctx = new AudioContextCtor({ sampleRate: SAMPLE_RATE });
        await ctx.resume(); // Ensure context is running immediately
        audioContext.value = ctx;
        nextAudioStartTime = ctx.currentTime;

        // 4. Connect WebSocket
        const url = `wss://api.openai.com/v1/realtime?model=${REALTIME_MODEL}`;
        const socket = new WebSocket(url, [
            'realtime',
            `openai-insecure-api-key.${clientSecret}`,
            'openai-beta.realtime-v1',
        ]);
        ws.value = socket;

        socket.onopen = async () => {
            isConnected.value = true;
            statusMessage.value = 'Listening...';
            // Start Microphone Stream
            await startAudioStreaming(ctx, socket);

            // Trigger initial response (greeting)
            socket.send(JSON.stringify({
                type: 'response.create'
            }));
        };

        socket.onmessage = (event) => {
            try {
                const msg = JSON.parse(event.data);
                handleWebSocketMessage(msg);
            } catch (err) {
                console.error('Error parsing WS message:', err);
            }
        };

        socket.onerror = (err) => {
            console.error('WebSocket Error:', err);
            statusMessage.value = 'Connection Error';
            cleanupSession();
        };

        socket.onclose = (event) => {
            console.log('WebSocket closed details:', {
                code: event.code,
                reason: event.reason,
                wasClean: event.wasClean,
                timestamp: new Date().toISOString()
            });

            if (event.code === 1006) {
                 console.error('ABNORMAL KEEPALIVE TIMEOUT or FRAME ERROR (1006)');
                 // Provide a more descriptive error based on common 1006 causes
                 statusMessage.value = 'Connection unstable (Check Network/Buffer)';
            } else if (isRecording.value) {
                statusMessage.value = `Disconnected (Code: ${event.code})`;
            }

            if (isRecording.value) {
                cleanupSession();
            }
        };

    } catch (error: any) {
        console.error('Failed to start conversation:', error);
        if (axios.isAxiosError(error) && error.response?.status === 401) {
            statusMessage.value = 'Session expired. Please sign in again.';
            emits('error', 'Unauthenticated. Please sign in again.');
        } else {
            statusMessage.value = 'Error starting session';
            emits('error', error.message || 'Could not start session');
        }
        cleanupSession();
    }
}

async function stopConversation(reason = 'User stopped') {
    statusMessage.value = `Stopped: ${reason}`;
    await cleanupSession();
}

// --- Audio Handling ---

async function startAudioStreaming(ctx: AudioContext, socket: WebSocket) {
    try {
        // Get Mic Stream
        const stream = await navigator.mediaDevices.getUserMedia({
            audio: {
                channelCount: 1,
                sampleRate: SAMPLE_RATE,
                echoCancellation: true,
                noiseSuppression: true,
            }
        });
        mediaStream.value = stream;

        // Create Source
        const source = ctx.createMediaStreamSource(stream);
        inputSource.value = source;

        // Create Processor (PCM16 chunking)
        const processor = ctx.createScriptProcessor(4096, 1, 1);
        audioProcessor.value = processor;

        // Buffer for accumulating audio chunks to reduce WS frame rate
        let transmissionBuffer: Int16Array[] = [];
        let transmissionBufferLength = 0;
        const TARGET_BUFFER_SIZE = 4096 * 3; // Approx 0.5s of audio at 24kHz

        processor.onaudioprocess = (e) => {
            if (socket.readyState !== WebSocket.OPEN) return;

            // SAFETY: Prevent socket buffer saturation
            if (socket.bufferedAmount > 256 * 1024) {
                 if (Math.random() < 0.05) console.warn('Socket buffer full, dropping frame.');
                 return;
            }

            // ECHO CANCELLATION / HALF-DUPLEX:
            // If the assistant is currently speaking (or queued to speak), mute the mic.
            // This prevents echo from triggering the VAD and causing self-interruption.
            // We add a small buffer (100ms) to ensure residual echo is gone.
            if (audioContext.value && nextAudioStartTime > (audioContext.value.currentTime + 0.1)) {
                return;
            }

            try {
                let inputData = e.inputBuffer.getChannelData(0);
                let pcm16: Int16Array;

                // Combined Downsampling & Conversion Loop for Efficiency
                // Avoids creating intermediate Float32Arrays
                if (ctx.sampleRate > 25000) {
                    const ratio = Math.ceil(ctx.sampleRate / SAMPLE_RATE);
                    const newLength = Math.floor(inputData.length / ratio);
                    pcm16 = new Int16Array(newLength);
                    
                    for (let i = 0; i < newLength; i++) {
                        const s = Math.max(-1, Math.min(1, inputData[i * ratio]));
                        pcm16[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
                    }
                } else {
                    pcm16 = new Int16Array(inputData.length);
                    for (let i = 0; i < inputData.length; i++) {
                        const s = Math.max(-1, Math.min(1, inputData[i]));
                        pcm16[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
                    }
                }

                // Buffer audio
                transmissionBuffer.push(pcm16);
                transmissionBufferLength += pcm16.length;

                // Only send when buffer is full enough (reduces WS overhead)
                if (transmissionBufferLength >= TARGET_BUFFER_SIZE) {
                    // Flatten buffer
                    const combined = new Int16Array(transmissionBufferLength);
                    let offset = 0;
                    for (const chunk of transmissionBuffer) {
                        combined.set(chunk, offset);
                        offset += chunk.length;
                    }

                    // Convert and send
                    const base64Audio = arrayBufferToBase64(combined.buffer as ArrayBuffer);
                    socket.send(JSON.stringify({
                        type: 'input_audio_buffer.append',
                        audio: base64Audio,
                    }));

                    // Reset buffer
                    transmissionBuffer = [];
                    transmissionBufferLength = 0;
                }

            } catch (err) {
                console.error('Error in audio processing:', err);
            }
        };


        source.connect(processor);
        processor.connect(ctx.destination);

    } catch (err) {
        console.error('Audio streaming setup failed:', err);
        throw err;
    }
}



// --- Message Handling ---

function handleWebSocketMessage(msg: any) {
    // Log all incoming messages to debug
    console.log('WebSocket message received:', msg.type, msg);
    
    switch (msg.type) {
        case 'input_audio_buffer.speech_started':
            statusMessage.value = 'Speaking...';
            break;
            
        case 'input_audio_buffer.speech_stopped':
            statusMessage.value = 'Processing...';
            break;

        case 'response.audio.delta':
            statusMessage.value = 'Assistant speaking...';
            // Play incoming audio
            if (msg.delta && audioContext.value) {
                queueAudioForPlayback(msg.delta);
            }
            break;

        case 'response.audio_transcript.delta':
             // Optional: Real-time text display could go here
             break;

        case 'response.audio_transcript.done':
            // Capture assistant speech for the transcript
            if (msg.transcript) {
                 transcriptHistory.value += `Assistant: ${msg.transcript}\n`;
            }
            break;
        
        case 'conversation.item.input_audio_transcription.completed':
             if (msg.transcript) {
                 transcriptHistory.value += `User: ${msg.transcript}\n`;
             }
             break;

        case 'response.done':
            // Check for final JSON output in the response text
            if (msg.response?.output) {
                console.log('Realtime Response Output:', JSON.stringify(msg.response.output, null, 2)); // DEBUG LOG
                for (const item of msg.response.output) {
                    if (item.type === 'message' && item.content) {
                        for (const contentPart of item.content) {
                            if (contentPart.type === 'text' && contentPart.text) {
                                console.log('Scanning text content for JSON:', contentPart.text); // DEBUG LOG
                                checkForJsonPayload(contentPart.text);
                            } else if (contentPart.type === 'audio' && contentPart.transcript) {
                                console.log('Scanning audio transcript for JSON:', contentPart.transcript); // DEBUG LOG
                                checkForJsonPayload(contentPart.transcript);
                            }
                        }
                    }
                }
            }
            break;
            
        case 'response.output_item.done':
            console.log('🔔 response.output_item.done event received!');
            console.log('Item type:', msg.item?.type);
            console.log('Full item:', JSON.stringify(msg.item, null, 2));
            
            // Handle function call containing episode data
            if (msg.item?.type === 'function_call') {
                console.log('✅ Function call detected!');
                console.log('Function name:', msg.item.name);
                console.log('Arguments:', msg.item.arguments);
                
                // Check if this is our log_migraine_episode function
                if (msg.item.name === 'log_migraine_episode') {
                    console.log('🎯 log_migraine_episode function called!');
                    
                    if (msg.item.arguments) {
                        try {
                            // Parse the arguments - it might be a JSON string or already an object
                            const episodeData = typeof msg.item.arguments === 'string' 
                                ? JSON.parse(msg.item.arguments) 
                                : msg.item.arguments;
                            
                            console.log('📦 Parsed episode data from function:', episodeData);
                            
                            // Disable audio output immediately
                            audioOutputEnabled.value = false;
                            
                            // Save the episode data
                            saveEpisode(episodeData);
                        } catch (e) {
                            console.error('❌ Error parsing function call arguments:', e);
                        }
                    } else {
                        console.log('⚠️ No arguments in log_migraine_episode call');
                    }
                } else {
                    console.log(`ℹ️ Different function called: ${msg.item.name}, ignoring`);
                }
            } else {
                console.log('ℹ️ Item is not function_call, ignoring');
            }
            break;
            
        case 'error':
            console.error('Realtime API Error (Server-side):', msg.error);
            if (msg.error?.code === 'session_expired') {
                statusMessage.value = 'Session Expired';
                cleanupSession();
            }
            break;
            
        case 'session.created':
            console.log('Session Created:', msg.session);
            break;
            
        case 'session.updated':
             console.log('Session Updated:', msg.session);
             break;
    }
}

// --- Audio Playback ---

const audioOutputEnabled = ref(true);

function queueAudioForPlayback(base64Delta: string) {
    if (!audioOutputEnabled.value) return; // Guard: Block audio if JSON detected
    if (!audioContext.value) return;

    try {
        const pcm16 = base64ToArrayBuffer(base64Delta);
        const float32 = pcm16ToFloat32(pcm16);
        
        const buffer = audioContext.value.createBuffer(1, float32.length, SAMPLE_RATE);
        buffer.getChannelData(0).set(float32);

        const source = audioContext.value.createBufferSource();
        source.buffer = buffer;
        source.connect(audioContext.value.destination);

        // Schedule to play immediately after the previous chunk
        // Ensure strictly monotonic time to prevent overlap/gaps
        const currentTime = audioContext.value.currentTime;
        if (nextAudioStartTime < currentTime) {
            nextAudioStartTime = currentTime;
        }
        
        source.start(nextAudioStartTime);
        nextAudioStartTime += buffer.duration;
    } catch (e) {
        console.error('Error queuing audio playback:', e);
    }
}

function checkForJsonPayload(text: string) {
    // Attempt to extract JSON from the text
    // The prompt ensures a specific JSON format.
    try {
        const jsonMatch = text.match(/\{[\s\S]*\}/);
        if (jsonMatch) {
            const jsonText = jsonMatch[0];
            console.log('Found potential JSON text:', jsonText); 
            const data = JSON.parse(jsonText);

            // Validate fields to ensure it's the episode data
            if (data.pain_location || data.intensity || data.start_time) {
                console.log('Detected Final Episode Data valid structure:', data); 
                
                // GUARD: Disable audio output immediately upon detecting final JSON
                audioOutputEnabled.value = false;
                
                saveEpisode(data);
            } else {
                console.log('JSON found but missing required keys:', data); 
            }
        } else {
             console.log('No JSON match found in text chunk.'); 
        }
    } catch (e) {
        // Not a JSON or partial JSON
        // console.warn('JSON parse error or structure mismatch:', e); 
    }
}

async function saveEpisode(data: StructuredEpisode) {
    console.log('Attempting to save episode...', isSavingEpisode.value); // DEBUG LOG
    if (isSavingEpisode.value) return;
    isSavingEpisode.value = true;
    
    const user = page.props.auth?.user;
    if (!user) {
        statusMessage.value = 'Session expired. Please sign in again.';
        realtimeError.value = 'Unauthenticated. Please sign in again.';
        emits('error', 'Unauthenticated. Please sign in again.');
        isSavingEpisode.value = false;
        return;
    }
    
    // DELAY FIX: Short delay to ensure state settles, but audio is already blocked
    setTimeout(() => {
        stopConversation('Episode Collected');
    }, 1000);

    try {
        const payload = {
            ...data,
            transcript_text: transcriptHistory.value // Corrected key for backend
        };
        
        console.log('Sending payload to backend:', payload); // DEBUG LOG

        const response = await axios.post('/episodes', payload);

        console.log('Backend response:', response.data); // DEBUG LOG

        const episodeId = response.data.episode?.id; // Corrected ID access path

        // Emitting complete allows the parent component to react if needed
        emits('conversation-complete', {
            episodeId: episodeId,
            structured: data,
            transcript: transcriptHistory.value,
            message: 'Saved successfully'
        });



    } catch (err: any) {
        console.error('Error saving episode:', err);
        if (axios.isAxiosError(err) && err.response?.status === 401) {
            statusMessage.value = 'Session expired. Please sign in again.';
            realtimeError.value = 'Unauthenticated. Please sign in again.';
            emits('error', 'Unauthenticated. Please sign in again.');
        } else {
            if (err.response) {
                console.error('Backend error response:', err.response.data);
            }
            statusMessage.value = 'Error saving data';
            realtimeError.value = err.response?.data?.message || 'Failed to save episode.';
        }
    } finally {
        isSavingEpisode.value = false;
    }
}


// --- Cleanup ---

async function cleanupSession() {
    isRecording.value = false;
    isConnected.value = false;
    nextAudioStartTime = 0;

    // Close WebSocket
    if (ws.value) {
        ws.value.close();
        ws.value = null;
    }

    // Stop Audio Processing
    if (audioProcessor.value) {
        audioProcessor.value.disconnect();
        audioProcessor.value = null;
    }

    if (inputSource.value) {
        inputSource.value.disconnect();
        inputSource.value = null;
    }

    // Stop Streams
    if (mediaStream.value) {
        mediaStream.value.getTracks().forEach(t => t.stop());
        mediaStream.value = null;
    }

    // Close Audio Context
    if (audioContext.value) {
        if (audioContext.value.state !== 'closed') {
            await audioContext.value.close();
        }
        audioContext.value = null;
    }
    
    // Reset Audio Guard
    audioOutputEnabled.value = true;
}

// --- Utils ---

function floatToPCM16(input: Float32Array): Int16Array {
    const output = new Int16Array(input.length);
    for (let i = 0; i < input.length; i++) {
        const s = Math.max(-1, Math.min(1, input[i]));
        output[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
    }
    return output;
}

function arrayBufferToBase64(buffer: ArrayBuffer): string {
    let binary = '';
    const bytes = new Uint8Array(buffer);
    const len = bytes.byteLength;
    const chunkSize = 0x8000; // 32KB chunks
    
    // Chunked processing prevents stack overflow and reduces main thread blocking risk
    for (let i = 0; i < len; i += chunkSize) {
        const chunk = bytes.subarray(i, Math.min(i + chunkSize, len));
        binary += String.fromCharCode.apply(null, chunk as unknown as number[]);
    }
    return window.btoa(binary);
}

function base64ToArrayBuffer(base64: string): ArrayBuffer {
    const binaryString = window.atob(base64);
    const len = binaryString.length;
    const bytes = new Uint8Array(len);
    for (let i = 0; i < len; i++) {
        bytes[i] = binaryString.charCodeAt(i);
    }
    return bytes.buffer;
}

function pcm16ToFloat32(buffer: ArrayBuffer): Float32Array {
    const int16 = new Int16Array(buffer);
    const float32 = new Float32Array(int16.length);
    for (let i = 0; i < int16.length; i++) {
        float32[i] = int16[i] / 32768.0;
    }
    return float32;
}

// Expose start/stop to parent via ref (Standard pattern in this codebase?)
// Checking if previous code used defineExpose.
// The previous code did NOT use defineExpose but the method names `startConversation` matched.
// Usually Components are used via template, but sometimes referenced.
// I will expose them just in case.
defineExpose({
    startConversation,
    stopConversation
});

</script>
<template>
    <div class="flex flex-col gap-4 rounded-[24px] border border-[--color-border]/70 bg-[rgba(15,21,18,0.75)] p-6">
        <!-- Header -->
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-lg font-semibold text-white">Voice Logger</p>
                <p class="text-sm text-[--color-text-muted]">
                    Speak naturally about your migraine. The AI will guide you found.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    v-if="conversationActive"
                    type="button"
                    class="rounded-full border border-[--color-accent]/60 bg-[rgba(97,216,118,0.15)] px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-[--color-accent]"
                    @click="stopConversation('Stopped by user')"
                >
                    Stop
                </button>
            </div>
        </div>

        <!-- Main Recording Area -->
        <div class="flex flex-col items-center gap-3 rounded-[20px] border border-[--color-border]/60 bg-[rgba(12,18,15,0.7)] px-6 py-8 text-center">
            <!-- Record Button -->
            <button
                type="button"
                :disabled="!canStart && !conversationActive"
                class="group relative flex h-24 w-24 items-center justify-center rounded-full border-2 transition"
                :class="[
                    conversationActive
                        ? 'border-red-400/70 bg-red-500/15 text-red-200 shadow-[0_0_24px_rgba(239,68,68,0.35)]'
                        : canStart
                            ? 'border-[--color-accent]/55 bg-[rgba(15,21,18,0.9)] text-[--color-accent] hover:border-[--color-accent] hover:shadow-[0_0_24px_rgba(97,216,118,0.3)]'
                            : 'border-white/10 text-white/40 opacity-60 cursor-not-allowed'
                ]"
                @click="conversationActive ? stopConversation('Stopped by user') : startConversation()"
            >
                <!-- Pulse animation when recording -->
                <span
                    v-if="conversationActive && !isSpeaking"
                    class="pointer-events-none absolute inset-0 rounded-full border border-red-400/40 animate-ring"
                />
                
                <!-- Speaking indicator -->
                <span
                    v-if="isSpeaking"
                    class="pointer-events-none absolute inset-0 rounded-full border-2 border-[--color-accent]/60 animate-pulse"
                />

                <!-- Icon -->
                <svg
                    v-if="conversationActive"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    class="h-12 w-12"
                    :class="isSpeaking ? 'text-[--color-accent]' : 'text-red-200'"
                    fill="none"
                >
                    <rect x="7" y="7" width="10" height="10" rx="2" fill="currentColor" />
                </svg>
                <svg
                    v-else
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    class="h-12 w-12"
                    fill="none"
                >
                    <path
                        d="M12 4a3 3 0 0 0-3 3v5a3 3 0 0 0 6 0V7a3 3 0 0 0-3-3Z"
                        stroke="currentColor"
                        stroke-width="1.6"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <path
                        d="M7 11a5 5 0 0 0 10 0"
                        stroke="currentColor"
                        stroke-width="1.6"
                        stroke-linecap="round"
                    />
                    <path d="M12 18v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                    <path d="M8 21h8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                </svg>
            </button>

            <!-- Status Message -->
            <div class="w-full">
                <p class="mt-2 text-xs uppercase tracking-[0.2em] text-[--color-text-muted]">
                    {{ statusMessage }}
                </p>
            </div>

            <!-- Transcript Display -->
            <div class="w-full space-y-2 rounded-[16px] border border-[--color-border]/70 bg-[rgba(10,14,12,0.8)] p-4 text-left">
                <!-- Guidance Text (show when no transcript yet) -->
                <p v-if="!conversationActive || !displayTranscript" class="text-sm text-[--color-text-muted] italic">
                    Share what happened in your own words.
                </p>

                <!-- Error Display -->
                <p v-if="realtimeError" class="text-[0.8rem] text-red-300 mt-2">
                    ⚠️ {{ realtimeError }}
                </p>

                <!-- Saving Indicator -->
                <p v-if="isSavingEpisode" class="text-[0.8rem] text-[--color-accent] mt-2">
                    💾 Saving episode...
                </p>

                <!-- Success Message -->
                <p v-if="hasSavedEpisode" class="text-[0.9rem] text-green-400 mt-2">
                    ✅ Episode saved successfully!
                </p>
            </div>
        </div>

        <!-- Status Messages -->
        <div v-if="errorMessage" class="rounded-lg bg-red-500/10 border border-red-500/30 p-3">
            <p class="text-sm text-red-300">{{ errorMessage }}</p>
        </div>
    </div>
</template>

<style scoped>
@keyframes ringPulse {
    0% {
        transform: scale(1);
        opacity: 0.5;
    }
    70% {
        transform: scale(1.25);
        opacity: 0;
    }
    100% {
        transform: scale(1.25);
        opacity: 0;
    }
}

.animate-ring {
    animation: ringPulse 1.6s ease-out infinite;
    box-shadow: 0 0 0 1px rgba(248, 113, 113, 0.35);
}
</style>
