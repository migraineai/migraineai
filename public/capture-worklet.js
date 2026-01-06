class CaptureProcessor extends AudioWorkletProcessor {
    process(inputs) {
        const [input] = inputs;
        if (!input || input.length === 0) {
            return true;
        }

        const channel = input[0];
        if (!channel || channel.length === 0) {
            return true;
        }

        // Copy the buffer because the underlying memory is reused by the audio system.
        this.port.postMessage({
            type: 'chunk',
            samples: channel.slice(0),
        });

        return true;
    }
}

registerProcessor('capture-worklet', CaptureProcessor);
