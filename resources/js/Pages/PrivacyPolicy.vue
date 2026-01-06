<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

type ContentBlock =
    | {
          type: 'paragraph';
          text: string;
      }
    | {
          type: 'list';
          items: string[];
      };

type PolicySection = {
    id: string;
    title: string;
    summary: string;
    content: ContentBlock[];
};

const sections: PolicySection[] = [
    {
        id: 'introduction',
        title: '1. Introduction and Overview',
        summary:
            'In short: This Privacy Policy explains how Migraine AI collects, uses, and protects your personal information. It applies to everyone who uses our web and mobile app worldwide. We have written each section in plain language (so teens and parents can easily understand) followed by more detailed legal terms. Migraine AI is a wellness tracking tool to help log migraines and habits - it is not a doctor or medical device. Using Migraine AI means you agree to this Privacy Policy and our practices described here.',
        content: [
            {
                type: 'paragraph',
                text: 'Detailed Policy: This Privacy Policy ("Policy") describes how Migraine AI Private Limited ("Migraine AI", "we", "our", or "us") collects, uses, shares, and safeguards personal data of users of our Migraine AI application and related services (the "Service"). It applies to all users worldwide, including teens (ages 13-17) and adults. We present this Policy in a dual-layer format: each section begins with a simple summary for clarity, followed by the full details in legal language. This layered approach is intended to help younger users and parents understand our data practices while still providing all required legal information.'
            },
            {
                type: 'paragraph',
                text: 'Migraine AI is provided by Migraine AI Private Limited, an Indian company located at BLOCK H201 SHILP SHALIGRAM, B/H AHMEDABAD ONE MALL, Memnagar, Ahmedabad City, Ahmedabad-380052, Gujarat, and is offered globally as a freemium Software-as-a-Service (SaaS) platform. Importantly, Migraine AI is not a medical provider or device - it is a wellness and symptom tracking tool intended for informational and self-management purposes only. Migraine AI does not offer medical advice, diagnosis, or treatment. Any health-related insights we provide are for personal wellness tracking and do not replace professional medical judgment. Migraine AI is not a covered entity or business associate under the U.S. Health Insurance Portability and Accountability Act (HIPAA). We do not handle protected health information (PHI) as defined under HIPAA, and no Business Associate Agreement (BAA) is required for our general wellness services. However, if you choose to share data with healthcare providers, you are responsible for ensuring HIPAA compliance on their end. By using our Service, you acknowledge this non-medical nature and agree that you will consult qualified healthcare professionals for any medical issues.'
            },
            {
                type: 'paragraph',
                text: 'This Policy covers all users of Migraine AI, including those accessing our mobile app, website, and any associated services. If you do not agree with our practices, please do not use the Service. For any questions or concerns about this Policy, you can contact us at support@migraineai.com or as described in the How to Contact Us section below.'
            }
        ]
    },
    {
        id: 'age-specific',
        title: '2. Age-Specific Data Collection and Protections',
        summary:
            'In short: We handle personal data differently depending on your age, and we give extra protection to anyone under 18. Migraine AI is designed for users aged 13 and up. If you are under 18, you must have a parent or guardian\'s permission to use Migraine AI, and they can supervise your account. We comply with child privacy laws like COPPA (which protects kids under 13 in the U.S.) and GDPR\'s rules for minors in the EU. We collect only the minimum data needed from teens, and we do not keep it longer than necessary. We also do not allow any targeted advertising or selling of data for users under 18. Parents have tools (like email/OTP verification and a parent dashboard) to review and control their child\'s data and can withdraw consent at any time.',
        content: [
            {
                type: 'paragraph',
                text: 'Detailed Policy: Migraine AI is not directed to children under 13, and we do not knowingly collect personal information from anyone under the age of 13 without verifiable parental consent, in compliance with the U.S. Children\'s Online Privacy Protection Act (COPPA). The Service is intended for users who are 13 years of age or older. If you are under the age of 13, you may not create an account or use Migraine AI unless your parent or legal guardian has provided verified consent through our parental consent process (for example, via email verification followed by OTP confirmation). If we learn that we have inadvertently collected personal data from a child under 13 without proper consent, we will delete that data as required by law.'
            },
            {
                type: 'paragraph',
                text: 'For teen users between 13 and 17 years old, additional protections apply. Users under 18 are considered minors for the purposes of privacy and data protection, and in many jurisdictions (including India and the EU) are legally classified as children. Migraine AI requires parental consent for all users under 18. During the signup process, if you indicate your age is under 18, we will ask for a parent or guardian\'s email address. We will reach out to the parent or guardian to obtain verifiable parental consent (for example, email verification plus OTP or ID upload) before permitting full use of the Service. The parent or guardian must confirm the account and agree to this Policy and our Terms on the minor\'s behalf. Until we receive this consent, the minor\'s account may remain in a limited state (or be temporarily suspended) and may be subject to deletion if consent is not provided within a reasonable time (for example, 7 days).'
            },
            {
                type: 'paragraph',
                text: 'Parental Dashboard and Controls: Once parental consent is confirmed, the parent or guardian will have access to certain supervisory controls. We provide a parental dashboard or linked account feature that allows the parent to review the minor\'s profile information and settings, monitor general usage (for example, number of migraine entries logged), and manage the child\'s account. We do not share the child\'s detailed migraine journal entries or sensitive wellness notes with the parent through the dashboard by default, in order to respect the teen\'s privacy, unless the parent requests such access or the teen chooses to share it. However, parents can always contact us to obtain or delete their child\'s personal data, as permitted by law. Parents or guardians have the right to withdraw consent at any time. If a parent withdraws consent, we will deactivate and delete the minor\'s account and data within 72 hours, consistent with applicable law.'
            },
            {
                type: 'paragraph',
                text: 'Legal Compliance for Minors: Migraine AI complies with all applicable laws and regulations regarding children\'s data privacy, including but not limited to:'
            },
            {
                type: 'list',
                items: [
                    'COPPA (U.S.) - We obtain verified parental consent before collecting personal data from children under 13, and we meet COPPA\'s requirements for data handling and parent rights. If consent is not obtained, we do not collect or use the child\'s data.',
                    'GDPR (EU) and UK GDPR - In the European Union, where GDPR applies, we recognize that each member state may set a digital age of consent between 13 and 16. We do not allow children under the age of consent in their country to use our Service without parental authorization. In any case, if you are under 16 in the EU, your parent or guardian must consent to your use of Migraine AI. We make reasonable efforts to verify that the person providing consent holds parental responsibility. This follows GDPR Article 8, which makes processing of a child\'s data lawful only if the child is at least 16, or if younger, a parent or guardian has authorized it (with 13 as the absolute minimum age allowed). In the UK, we similarly require parental consent for users under 13, consistent with UK GDPR.',
                    'India\'s DPDP Act - Under India\'s Digital Personal Data Protection Act, 2023 (as amended by 2025 Rules), anyone under 18 is considered a child and requires verifiable parental consent for their data to be processed. We implement technical measures (for example, OTP or email) to ensure a parent or guardian has consented to the account creation and data processing for users under 18. We also adhere to India\'s requirements to avoid any harmful processing of children\'s data - we do not process a child\'s personal data in a way that is likely to cause harm or have a detrimental effect on the child\'s well-being. In addition, Migraine AI does not engage in any behavioral tracking or targeted advertising directed at children, as prohibited by applicable law.'
                ]
            },
            {
                type: 'paragraph',
                text: 'Data Minimization for Minors: We collect only the data from minor users that is necessary to provide the Service (see the What Data We Collect section below for details) and we apply enhanced privacy defaults for minors. For example, certain features that may involve broader data sharing or public profiles are disabled or limited for users under 18. We do not solicit unnecessary personal details from minors such as precise location or contact details beyond what is needed for the account and parental contact. Any wellness or health-related tracking data a teen enters (like migraine logs) is used solely for providing the Service to that teen and is not used for marketing or profiling.'
            },
            {
                type: 'paragraph',
                text: 'Shorter Retention for Minors: Migraine AI has shorter data retention periods for minors (see How We Store and Protect Your Data). In brief, we generally retain a minor user\'s personal data for no longer than 12 months of inactivity unless we receive consent from the parent (or the user, once adult) to retain it longer. This helps minimize the risks associated with long-term storage of children\'s data.'
            },
            {
                type: 'paragraph',
                text: 'By using Migraine AI as a minor (with parental consent), you and your parent or guardian acknowledge that we will collect and use your information as described in this Policy. We strongly encourage parents and guardians to discuss this Policy with their children to ensure understanding. If you have questions about our children\'s privacy measures, you can contact us (see How to Contact Us).'
            }
        ]
    },
    {
        id: 'data-collection',
        title: '3. What Data We Collect',
        summary:
            'In short: We collect different types of information to help you use Migraine AI. Most of it comes directly from you: for example, when you create an account you provide an email and age, and when you use the app you enter your migraine logs (like how bad your headache is and what triggers you noticed). We also collect some information automatically to make the app work, like technical details (your device type, app version, and IP address) and how you navigate through the app. If you connect other services or devices (say a fitness tracker for sleep data), we collect that data with your permission. We use cookies and analytics tools (like Google Analytics) to understand how people use Migraine AI (for example, which features are popular), but we do not collect things like your precise location or any data we do not need.',
        content: [
            {
                type: 'paragraph',
                text: 'Detailed Policy: Migraine AI collects several categories of personal data from and about users. The types of information we collect include:'
            },
            {
                type: 'list',
                items: [
                    'Account Information: When you sign up for Migraine AI, we ask for basic account details. This includes your email address, a password (which is stored in a hashed or encrypted form), and your date of birth (or age) to verify eligibility and apply the appropriate privacy protections for minors. We may also collect your name or nickname if you choose to provide it for your profile, and your subscription plan details (for example, free or paid tier). If you are a minor user, we additionally collect parent or guardian contact information (such as a parent\'s email address) as part of the consent process.',
                    'Migraine and Wellness Logs: The core of our Service is allowing you to log information about your migraines and related wellness factors. This data is provided voluntarily by you and may include details such as migraine diary entries (for example, date and time of headache, duration, pain intensity, symptoms experienced), potential triggers or lifestyle factors (for example, foods eaten, stress levels, sleep duration, weather conditions), wellness habits and activities (for example, exercise, water intake, medications taken, mood), and any free-form notes you add about your condition or feelings. This information can be considered health-related data (though for legal clarity we note it is self-reported wellness information, not medical records or PHI under HIPAA). We treat it with a high level of privacy and security due to its sensitive nature. You control how much detail you log - all such data is optional and can be edited or deleted by you at any time.',
                    'Device and Technical Information: We automatically collect certain information about the devices you use to access Migraine AI and how the Service is functioning. This includes device identifiers and attributes (such as your device type or model, operating system version, unique device ID or advertising ID if applicable, browser type for web users, and app version), IP address and general geolocation, and log and usage data (such as timestamps of logins, pages or screens viewed, features used, clicks and navigation within the app, and errors or crash reports). This helps us troubleshoot issues and understand usage patterns to improve the Service.',
                    'Cookies and Similar Technologies: When you visit our website or use the Migraine AI web app, we use cookies and similar tracking technologies (like local storage and pixels). These technologies collect data such as session cookies to keep you logged in and remember your preferences (for example, your language or display settings) and analytics cookies (from third parties like Google Analytics) that track user interactions in aggregate to help us understand and improve user experience. These cookies may collect your browser information and assign a unique anonymous ID to your session. We do not use any advertising or marketing cookies that track you across other sites, and we do not allow third-party ad networks to collect data about you through our Service.',
                    'Third-Party Integrations Data: Migraine AI may offer optional integrations with other apps or devices to enhance your tracking experience. For example, you might have the option to connect a fitness tracker or health app (such as a smartwatch, Google Fit, or Apple Health) to import data like your daily step count, sleep patterns, or heart rate, since these can be relevant to migraines. If you choose to connect an integration, we will explicitly ask your permission and explain what data will be imported. By enabling integrations, you authorize Migraine AI to collect and use the provided third-party data within the app for your wellness tracking. You can revoke access at any time via the app or your device settings. Migraine AI is not responsible for the accuracy or privacy practices of third-party data sources.'
                ]
            },
            {
                type: 'paragraph',
                text: 'We do not collect sensitive personal data beyond what is necessary for the Service, such as financial information (except for billing via third-party processors) or biometric data without explicit consent. All collection is minimized and purpose-limited per the DPDP Act and GDPR.'
            }
        ]
    },
    {
        id: 'data-use',
        title: '4. How We Use Your Data',
        summary:
            'In short: We use your information to run the app (like making your dashboard), improve it (spot bugs), and communicate with you (support emails). We anonymize data for trends (for example, "most users hate rainy days"). Legal requests are rare.',
        content: [
            {
                type: 'paragraph',
                text: 'Detailed Policy: We use collected data only for legitimate purposes, with your consent where required. Key uses include:'
            },
            {
                type: 'list',
                items: [
                    'Provide and Personalize the Service: Process logs for analytics, send reminders, and sync devices.',
                    'Improve and Analyze: Aggregate or de-identify data for trends without individual identifiers, and debug issues via usage logs.',
                    'Communicate: Send account alerts, support responses, and policy updates (you can opt out of promotional messages).',
                    'Security and Compliance: Detect fraud, verify age, and meet legal obligations such as maintaining DPDP consent logs.',
                    'Legal Bases: Consent (for minors and data sharing), contract (for service delivery), and legitimate interests (for analytics and security balanced against user rights).'
                ]
            },
            {
                type: 'paragraph',
                text: 'We do not engage in automated decisions with legal effects (for example, no denial of service based on AI insights). Data is not sold; we may use de-identified aggregates for research (such as migraine patterns studies) without including personal identifiers.'
            }
        ]
    },
    {
        id: 'data-sharing',
        title: '5. How We Share Your Data',
        summary:
            'In short: We share only if you say yes (for example, when you export to your doctor) or when we must (legal requests). We do not sell your data. Partners like our cloud hosts sign confidentiality agreements. Parents see high-level overviews.',
        content: [
            {
                type: 'paragraph',
                text: 'Detailed Policy: We share personal data only in limited situations:'
            },
            {
                type: 'list',
                items: [
                    'With Consent: Exports to clinicians or other recipients you authorize, and optional integrations you enable.',
                    'Service Providers: Encrypted hosting partners (for example, AWS regions in India or the EU, with Standard Contractual Clauses for transfers) and analytics providers (for example, Google Analytics with anonymization).',
                    'Parents and Minors: Parental dashboards provide overview insights for minors, without detailed journals by default unless access is requested or granted.',
                    'Legal: Compliance with court orders, lawful requests, or emergency circumstances (we notify you unless legally prohibited).',
                    'Business Transfers: If there is a merger, acquisition, or sale of assets, we will notify you and ensure continued protection.',
                    'No Sales: We do not sell personal data and comply with CCPA and DPDP obligations. Affiliates are bound by this Policy.'
                ]
            }
        ]
    },
    {
        id: 'international-transfers',
        title: '6. International Data Transfers',
        summary:
            'In short: We store data in India and the EU (your "local safe") and transfer it abroad only with required safeguards.',
        content: [
            {
                type: 'paragraph',
                text: 'Detailed Policy: Primary servers are located in India to align with DPDP requirements, with regional infrastructure in the EU for applicable users. When data is transferred outside these regions, we use safeguards such as Standard Contractual Clauses (for GDPR compliance), adequacy decisions where available, and explicit consent for minors when the law requires it. We do not conduct transfers prohibited under the DPDP 2025 Rules.'
            }
        ]
    },
    {
        id: 'data-security',
        title: '7. How We Store and Protect Your Data',
        summary:
            'In short: Your data is encrypted and access is tightly controlled. We keep it only as long as needed (deleting accounts inactive for 12-24 months depending on age).',
        content: [
            {
                type: 'paragraph',
                text: 'Detailed Policy: Migraine AI uses AES-256 encryption at rest and TLS in transit. Access controls, logging, and periodic audits limit who can view data. We retain data for the duration of your active use plus up to 12 months of inactivity (shorter for minors unless extended consent is provided). Backups are kept for 90 days before secure deletion.'
            },
            {
                type: 'paragraph',
                text: 'Security Incident Response: If a breach risks your rights, we will notify you and relevant authorities within 72 hours (aligned with GDPR and DPDP timelines). Please report suspected issues to support@migraineai.com.'
            }
        ]
    },
    {
        id: 'rights',
        title: '8. Your Rights and Choices',
        summary:
            'In short: You control your data. Access it, correct it, delete it, or opt out of certain uses. We respond within 30 days.',
        content: [
            {
                type: 'paragraph',
                text: 'Detailed Policy: Under laws such as the DPDP, GDPR, and CCPA, you may have rights to access, correct, delete, object to processing, or request portability of your personal data. Parents exercise these rights on behalf of minors.'
            },
            {
                type: 'paragraph',
                text: 'To exercise any rights, contact support@migraineai.com with enough information for us to verify your identity. We will respond within required timelines (typically 30 days). You will not be discriminated against for using your privacy rights. Cookie and analytics preferences can be managed through in-app settings or your browser controls.'
            }
        ]
    },
    {
        id: 'cookies',
        title: '9. Cookies and Tracking',
        summary:
            'In short: We use cookies that help the site function and measure usage; we do not run ads. You can opt out through our banner or your browser.',
        content: [
            {
                type: 'paragraph',
                text: 'Detailed Policy: Essential cookies keep you logged in and remember preferences. Analytics cookies (for example, from Google Analytics with IP anonymization) help us understand feature adoption and performance. We do not deploy cross-site advertising cookies. You can decline non-essential cookies through our consent tools or your browser settings.'
            }
        ]
    },
    {
        id: 'changes',
        title: '10. Changes to This Policy',
        summary:
            'In short: We will email you about major updates at least 30 days before they take effect.',
        content: [
            {
                type: 'paragraph',
                text: 'Detailed Policy: We post changes to this Policy on our website and web app. Material updates become effective on the date stated in the notice. Continued use of the Service after the effective date constitutes acceptance of the revised terms.'
            }
        ]
    },
    {
        id: 'contact',
        title: '11. How to Contact Us',
        summary:
            'In short: Reach out to our Data Protection Officer with any privacy questions or concerns.',
        content: [
            {
                type: 'paragraph',
                text: 'Migraine AI Private Limited'
            },
            {
                type: 'paragraph',
                text: 'BLOCK H201 SHILP SHALIGRAM, B/H AHMEDABAD ONE MALL, Memnagar, Ahmedabad City, Ahmedabad-380052, Gujarat'
            },
            {
                type: 'paragraph',
                text: 'Email: support@migraineai.com'
            },
            {
                type: 'paragraph',
                text: 'Attn: Data Protection Officer'
            },
            {
                type: 'paragraph',
                text: 'For EU residents: DPO at the above address. You may also contact your local supervisory authority to lodge a complaint.'
            }
        ]
    }
];
</script>

<template>
    <Head title="Privacy Policy" />

    <div class="min-h-screen bg-[#070d0a] text-[--color-text-primary]">
        <div
            aria-hidden="true"
            class="absolute inset-0 h-full w-full overflow-hidden"
        >
            <div
                class="absolute -top-48 left-1/2 h-[32rem] w-[36rem] -translate-x-1/2 rounded-full bg-[radial-gradient(circle_at_center,rgba(62,212,120,0.32),transparent_65%)] blur-3xl"
            />
            <div
                class="absolute bottom-0 right-[-12rem] h-[28rem] w-[28rem] rounded-full bg-[radial-gradient(circle_at_center,rgba(40,95,71,0.45),transparent_60%)] blur-3xl"
            />
            <div
                class="absolute left-[-18rem] top-1/3 h-[26rem] w-[26rem] rounded-full bg-[radial-gradient(circle_at_center,rgba(24,54,40,0.65),transparent_60%)] blur-3xl"
            />
        </div>

        <div class="relative">
            <header class="border-b border-[rgba(95,158,134,0.25)] bg-[rgba(7,15,11,0.82)]/90 backdrop-blur-xl">
                <div class="mx-auto flex max-w-6xl flex-col gap-6 px-6 py-8 md:flex-row md:items-center md:justify-between lg:px-0">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-2xl border border-[rgba(95,158,134,0.35)] bg-[rgba(8,18,14,0.65)] text-xl font-semibold text-[--color-accent]"
                        >
                            M
                        </div>
                        <div class="leading-tight">
                            <p class="text-sm uppercase tracking-[0.36em] text-[rgba(143,185,167,0.68)]">
                                Migraine AI
                            </p>
                            <p class="text-lg font-semibold">Privacy Policy</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-sm font-semibold uppercase tracking-[0.32em] text-[rgba(221,230,225,0.7)]">
                        <Link
                            href="/"
                            class="transition hover:text-[--color-accent]"
                        >
                            Home
                        </Link>
                        <a
                            href="#policy"
                            class="transition hover:text-[--color-accent]"
                        >
                            Policy
                        </a>
                        <Link
                            href="/register"
                            class="transition hover:text-[--color-accent]"
                        >
                            Get Started
                        </Link>
                    </div>
                </div>
            </header>

            <main class="relative z-10">
                <section
                    id="policy"
                    class="px-6 py-16 lg:px-0"
                >
                    <div class="mx-auto max-w-5xl space-y-16">
                        <div class="space-y-6 rounded-[36px] border border-[rgba(95,158,134,0.3)] bg-[rgba(8,18,14,0.78)] p-10 shadow-[0_28px_72px_rgba(0,0,0,0.55)]">
                            <div class="flex flex-wrap items-center justify-between gap-4 text-xs uppercase tracking-[0.36em] text-[rgba(143,185,167,0.65)]">
                                <span> Migraine AI Privacy Policy </span>
                                <span> Last Updated: October 5, 2025 </span>
                            </div>
                            <h1 class="text-3xl font-bold leading-tight text-[--color-accent] md:text-4xl">
                                Your data, cared for with intention.
                            </h1>
                            <p class="max-w-3xl text-[rgba(221,230,225,0.78)]">
                                We pair wellness insights with rigorous privacy standards. Each section below starts with a plain-language summary,
                                followed by the full legal detail so you understand exactly how Migraine AI works with your information.
                            </p>
                        </div>

                        <div class="space-y-12">
                            <article
                                v-for="section in sections"
                                :key="section.id"
                                :id="section.id"
                                class="rounded-[32px] border border-[rgba(95,158,134,0.28)] bg-[rgba(9,20,16,0.78)] p-8 shadow-[0_24px_60px_rgba(0,0,0,0.5)] transition hover:border-[rgba(110,254,172,0.35)]"
                            >
                                <p class="text-xs font-semibold uppercase tracking-[0.36em] text-[rgba(143,185,167,0.68)]">
                                    Section {{ section.title.split('.')[0] }}
                                </p>
                                <h2 class="mt-2 text-2xl font-semibold text-[--color-accent] md:text-3xl">
                                    {{ section.title }}
                                </h2>
                                <div class="mt-6 space-y-4 rounded-2xl border border-[rgba(95,158,134,0.32)] bg-[rgba(8,18,14,0.8)] p-6 text-sm text-[rgba(221,230,225,0.86)]">
                                    <h3 class="text-xs font-semibold uppercase tracking-[0.4em] text-[rgba(110,254,172,0.75)]">
                                        In Short
                                    </h3>
                                    <p>
                                        {{ section.summary }}
                                    </p>
                                </div>
                                <div class="mt-6 space-y-4 text-[rgba(221,230,225,0.8)]">
                                    <h3 class="text-xs font-semibold uppercase tracking-[0.4em] text-[rgba(143,185,167,0.68)]">
                                        Detailed Policy
                                    </h3>
                                    <template v-for="(block, index) in section.content" :key="`${section.id}-${index}`">
                                        <p
                                            v-if="block.type === 'paragraph'"
                                            class="leading-relaxed"
                                        >
                                            {{ block.text }}
                                        </p>
                                        <ul
                                            v-else
                                            class="space-y-3 rounded-2xl border border-[rgba(95,158,134,0.2)] bg-[rgba(8,18,14,0.75)] p-5 text-sm leading-relaxed text-[rgba(221,230,225,0.83)]"
                                        >
                                            <li
                                                v-for="item in block.items"
                                                :key="item"
                                                class="flex items-start gap-3"
                                            >
                                                <span
                                                    class="mt-1 block h-2 w-2 rounded-full bg-[--color-accent]"
                                                    aria-hidden="true"
                                                />
                                                <span class="leading-relaxed">
                                                    {{ item }}
                                                </span>
                                            </li>
                                        </ul>
                                    </template>
                                </div>
                            </article>
                        </div>

                        <div class="rounded-[32px] border border-[rgba(95,158,134,0.32)] bg-[rgba(8,18,14,0.82)] p-8 text-center text-[rgba(221,230,225,0.82)] shadow-[0_24px_60px_rgba(0,0,0,0.5)]">
                            <p class="text-sm font-semibold uppercase tracking-[0.4em] text-[rgba(110,254,172,0.75)]">
                                Thank You
                            </p>
                            <p class="mt-4 text-lg font-medium text-[--color-accent]">
                                Thank you for trusting Migraine AI - your wellness, our secure diary.
                            </p>
                            <p class="mt-3 text-sm">
                                Have additional questions? Our privacy team is ready to help at
                                <a
                                    href="mailto:support@migraineai.com"
                                    class="text-[--color-accent] underline-offset-4 hover:underline"
                                >
                                    support@migraineai.com
                                </a>
                                .
                            </p>
                        </div>
                    </div>
                </section>
            </main>

            <footer class="border-t border-[rgba(95,158,134,0.22)] bg-[rgba(7,15,11,0.82)]">
                <div class="mx-auto flex max-w-6xl flex-col gap-6 px-6 py-8 text-sm text-[rgba(221,230,225,0.65)] md:flex-row md:items-center md:justify-between lg:px-0">
                    <p>Design by Migraine AI - Crafted with calmness.</p>
                    <div class="flex flex-wrap items-center gap-4 uppercase tracking-[0.32em]">
                        <Link
                            href="/"
                            class="hover:text-[--color-accent]"
                        >
                            Home
                        </Link>
                        <Link
                            href="/login"
                            class="hover:text-[--color-accent]"
                        >
                            Login
                        </Link>
                        <Link
                            href="/register"
                            class="hover:text-[--color-accent]"
                        >
                            Register
                        </Link>
                    </div>
                </div>
            </footer>
        </div>
    </div>
</template>
