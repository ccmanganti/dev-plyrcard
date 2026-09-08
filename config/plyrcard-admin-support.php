<?php

return [
    'admin_roles' => [
        'Superadmin',
        'superadmin',
        'Super Admin',
        'Administrator',
        'Admin',
    ],

    'concerns' => [
        'getting_started' => [
            'label' => 'Log in and get started',
            'hint' => 'Account created but never opened.',
            'subject' => 'Your PLYRCARD is ready',
            'message' => <<<'TEXT'
Hey {{first_name}} — your PLYRCARD account is ready. Log in and take the first few steps so your recruiting profile is ready when coaches find you.

{{login_link}}

If you run into anything, reply to this message and we’ll help.

— {{admin_name}}
TEXT,
        ],
        'finish_profile' => [
            'label' => 'Finish your profile',
            'hint' => 'Profile is incomplete and coaches can see gaps.',
            'subject' => 'A few details will finish your PLYRCARD',
            'message' => <<<'TEXT'
Hey {{first_name}} — your profile is {{profile_completion}}% complete. The missing pieces are the ones coaches look at first. A few minutes gets you much closer to the finish line.

Complete your profile: {{profile_link}}

— {{admin_name}}
TEXT,
        ],
        'send_photo' => [
            'label' => 'Send us a photo',
            'hint' => 'No strong action/profile photo is on file.',
            'subject' => 'Let’s add a strong photo to your PLYRCARD',
            'message' => <<<'TEXT'
Hey {{first_name}} — we still need a strong photo for your PLYRCARD. A clear action or profile photo helps your page feel complete and gives coaches a better first impression.

Upload your photos here: {{photos_link}}

— {{admin_name}}
TEXT,
        ],
        'send_film' => [
            'label' => 'Send us your film',
            'hint' => 'No highlight or match film is available yet.',
            'subject' => 'Add film to your PLYRCARD',
            'message' => <<<'TEXT'
Hey {{first_name}} — film is one of the strongest things a coach can click from your profile. Add your latest highlight reel or match film so they have something current to evaluate.

Open your PLYRCARD: {{profile_link}}

— {{admin_name}}
TEXT,
        ],
        'start_outreach' => [
            'label' => 'Start your outreach',
            'hint' => 'Time to get your profile in front of coaches.',
            'subject' => 'Ready to start your coach outreach?',
            'message' => <<<'TEXT'
Hey {{first_name}} — your PLYRCARD is built to be shared, not just viewed. It’s a good time to start reaching out to coaches and building your target list.

Open Recruiting Center: {{outreach_link}}

— {{admin_name}}
TEXT,
        ],
        'payment_attention' => [
            'label' => 'Payment needs attention',
            'hint' => 'Billing or subscription status needs review.',
            'subject' => 'Your PLYRCARD billing needs attention',
            'message' => <<<'TEXT'
Hey {{first_name}} — we noticed your PLYRCARD billing status needs attention. Your current status is {{payment_status}}.

Review your billing details here: {{billing_link}}

If you’ve already updated it, you can ignore this message.

— {{admin_name}}
TEXT,
        ],
        'amplify_credits' => [
            'label' => 'Use your Amplify credits',
            'hint' => 'Remind an Amplify user to use included services.',
            'subject' => 'You still have Amplify value to use',
            'message' => <<<'TEXT'
Hey {{first_name}} — you’re on {{plan}}, and we want to make sure you’re getting the value from it. You still have Amplify services available to put toward your recruiting work.

Open your Locker Room: {{locker_room_link}}

Reply and tell us what you want to work on next.

— {{admin_name}}
TEXT,
        ],
        'renewal_coming_up' => [
            'label' => 'Renewal coming up',
            'hint' => 'Give the user a heads-up before renewal.',
            'subject' => 'A quick heads-up about your PLYRCARD renewal',
            'message' => <<<'TEXT'
Hey {{first_name}} — a quick heads-up that your PLYRCARD renewal is coming up {{renewal_date}}. You can review your plan and billing details before then.

{{billing_link}}

Questions? Reply here and we’ll help.

— {{admin_name}}
TEXT,
        ],
        'custom' => [
            'label' => 'Custom message',
            'hint' => 'Start with a simple editable message.',
            'subject' => 'A note from PLYRCARD',
            'message' => <<<'TEXT'
Hey {{first_name}} —

We wanted to reach out about your PLYRCARD account.

{{login_link}}

— {{admin_name}}
TEXT,
        ],
    ],

    'variables' => [
        'first_name' => 'First name',
        'full_name' => 'Full name',
        'email' => 'Account email',
        'phone' => 'Phone',
        'sport' => 'Sport',
        'school' => 'School',
        'plan' => 'Current plan',
        'profile_completion' => 'Profile completion %',
        'payment_status' => 'Payment/subscription status',
        'renewal_date' => 'Renewal date',
        'login_link' => 'Login link',
        'profile_link' => 'Profile link',
        'photos_link' => 'My Photos link',
        'outreach_link' => 'Recruiting Center link',
        'billing_link' => 'Billing link',
        'locker_room_link' => 'Locker Room link',
        'support_link' => 'Support link',
        'admin_name' => 'Admin name',
    ],
];
