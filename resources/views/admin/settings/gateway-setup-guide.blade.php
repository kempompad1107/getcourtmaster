@extends('layouts.app')
@section('title', 'Payment Gateway Setup Guide')

@section('content')

<x-page-header title="Payment Gateway Setup Guide">
    <x-slot:actions>
        <a href="{{ route('admin.settings.index') }}#gateways" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Settings
        </a>
    </x-slot:actions>
</x-page-header>

<div class="row justify-content-center" x-data="{ provider: 'paymongo' }">
    <div class="col-12 col-lg-10">

        {{-- Intro --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="fw-semibold mb-2"><i class="bi bi-info-circle me-2 text-primary"></i>How online payments work in {{ config('app.name') }}</h6>
                <p class="small mb-2">
                    Your venue connects <strong>your own PayMongo or Stripe account</strong>. When a customer
                    pays online, the money goes straight into <strong>your</strong> bank account — we never
                    hold or touch it. You handle payouts, refunds, and receipts on your own PayMongo or
                    Stripe dashboard.
                </p>
                <ul class="small mb-0 ps-3">
                    <li><strong>PayMongo</strong> — best for Philippine venues (GCash, Maya, QR Ph, local cards).</li>
                    <li><strong>Stripe</strong> — best if you accept international cards or have customers from abroad.</li>
                    <li>You can use both, just one, or stick with cash only — your choice.</li>
                </ul>
            </div>
        </div>

        {{-- Plain-language glossary --}}
        <div class="card border-0 shadow-sm mb-4 bg-light">
            <div class="card-body">
                <h6 class="fw-semibold mb-3"><i class="bi bi-book me-2 text-secondary"></i>Quick glossary — what these terms mean</h6>
                <div class="row g-3 small">
                    <div class="col-md-6">
                        <strong>Secret Key</strong>
                        <div class="text-muted">A long password from PayMongo or Stripe. {{ config('app.name') }} uses it to charge your customers on your behalf. Keep it private — don't share it.</div>
                    </div>
                    <div class="col-md-6">
                        <strong>Webhook</strong>
                        <div class="text-muted">An automatic message PayMongo / Stripe sends us the moment a customer's payment goes through. This is how wallets and bookings get marked "paid" right away.</div>
                    </div>
                    <div class="col-md-6">
                        <strong>Webhook URL</strong>
                        <div class="text-muted">The internet address where we receive those automatic messages. Each venue has its own unique URL — we'll give you one to paste into PayMongo or Stripe.</div>
                    </div>
                    <div class="col-md-6">
                        <strong>Webhook Secret</strong>
                        <div class="text-muted">A second password that lets us confirm an incoming message really came from PayMongo or Stripe (and not a hacker pretending to be them).</div>
                    </div>
                    <div class="col-md-6">
                        <strong>Test Mode vs Live Mode</strong>
                        <div class="text-muted">Test Mode lets you practice with fake money. Live Mode is real customers paying real money. This guide is for Live Mode.</div>
                    </div>
                    <div class="col-md-6">
                        <strong>KYC / Business Verification</strong>
                        <div class="text-muted">PayMongo and Stripe both need to confirm you're a real business before letting you accept real money. You'll upload IDs and business documents — it's a one-time process.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Provider switcher --}}
        <div class="btn-group w-100 mb-4" role="group">
            <button @click="provider = 'paymongo'"
                    :class="provider === 'paymongo' ? 'btn-primary' : 'btn-outline-primary'"
                    class="btn">
                <i class="bi bi-credit-card-2-front me-2"></i>PayMongo Setup
            </button>
            <button @click="provider = 'stripe'"
                    :class="provider === 'stripe' ? 'btn-primary' : 'btn-outline-primary'"
                    class="btn">
                <i class="bi bi-stripe me-2"></i>Stripe Setup
            </button>
        </div>

        {{-- ─────────────────── PayMongo ─────────────────── --}}
        <div x-show="provider === 'paymongo'" x-cloak>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">PayMongo Setup — Step by Step</h5>
                    <p class="small text-muted mb-0">Follow these 5 steps to start accepting real payments. PayMongo usually takes <strong>1–3 business days</strong> to verify your business documents — the rest of the setup only takes about 15 minutes of your time.</p>
                </div>
            </div>

            {{-- Step 1 --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <div class="badge bg-primary fs-6 rounded-circle" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center">1</div>
                        <div class="flex-grow-1">
                            <h6 class="fw-semibold mb-2">Sign up and verify your business</h6>
                            <ol class="small mb-2">
                                <li>Go to <a href="https://dashboard.paymongo.com/signup" target="_blank" rel="noopener">dashboard.paymongo.com/signup <i class="bi bi-box-arrow-up-right"></i></a> and sign up using your business email.</li>
                                <li>Confirm your email when PayMongo sends you a verification link.</li>
                                <li>Inside the PayMongo dashboard, click <strong>Settings → Business Information</strong> and upload:
                                    <ul>
                                        <li>Your business name, address, and contact info</li>
                                        <li>Your SEC or DTI registration (or BIR Form 2303 if you're a sole proprietor)</li>
                                        <li>A valid government ID of the business owner</li>
                                        <li>Your bank account details — this is where your earnings will be deposited</li>
                                    </ul>
                                </li>
                                <li>Wait for PayMongo's approval email (usually 1–3 business days).</li>
                            </ol>
                            <div class="alert alert-warning small mb-0">
                                Until PayMongo finishes verifying you, you can't accept real payments yet. Don't skip this step.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 2 --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <div class="badge bg-primary fs-6 rounded-circle" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center">2</div>
                        <div class="flex-grow-1">
                            <h6 class="fw-semibold mb-2">Turn on the payment options you want to offer</h6>
                            <ol class="small mb-2">
                                <li>In your PayMongo dashboard, look at the top-right corner and make sure the switch is set to <strong>Live</strong>.</li>
                                <li>Click <strong>Developers → Payment Methods</strong> from the left menu.</li>
                                <li>Turn on the options your customers will use:
                                    <ul>
                                        <li><strong>GCash</strong> — the most popular in the Philippines.</li>
                                        <li><strong>QR Ph</strong> — a single QR code that works for any Philippine bank or e-wallet.</li>
                                        <li><strong>Cards</strong> — credit and debit cards, including international ones.</li>
                                        <li><strong>Maya (PayMaya)</strong> — optional but nice to offer.</li>
                                    </ul>
                                </li>
                            </ol>
                            <div class="alert alert-warning small mb-0">
                                <strong>Heads up:</strong> Only options you turn on here will appear at checkout. If your customer sees "No payment methods available," it almost always means {{ config('app.name') }} asked for an option that wasn't turned on in your PayMongo dashboard.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 3 --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <div class="badge bg-primary fs-6 rounded-circle" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center">3</div>
                        <div class="flex-grow-1">
                            <h6 class="fw-semibold mb-2">Copy your Secret Key into {{ config('app.name') }}</h6>
                            <ol class="small mb-2">
                                <li>In PayMongo, make sure the top-right switch is on <strong>Live</strong>.</li>
                                <li>Click <strong>Developers → API Keys</strong>.</li>
                                <li>Copy the <strong>Secret Key</strong> — it must start with <code>sk_live_…</code>.</li>
                                <li>Open a new tab and go to <strong>{{ config('app.name') }} → Settings → Payments</strong>. Paste the key into the PayMongo <em>Secret key</em> box.</li>
                            </ol>
                            <div class="alert alert-danger small mb-0">
                                <strong>Treat your Secret Key like a password.</strong> Anyone who has it can take payments using your account. Don't email it, post it on chat, or include it in screenshots. We store it safely (encrypted) on our side.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 4: Webhook --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <div class="badge bg-primary fs-6 rounded-circle" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center">4</div>
                        <div class="flex-grow-1">
                            <h6 class="fw-semibold mb-2">Connect PayMongo to {{ config('app.name') }} (webhook)</h6>
                            <p class="small text-muted">This is what lets PayMongo tell {{ config('app.name') }} the moment a customer pays. Without it, wallets and bookings won't update automatically when a payment goes through.</p>
                            <ol class="small">
                                <li>In your PayMongo dashboard, make sure you're in <strong>Live</strong> mode, then click <strong>Developers → Webhooks → Add Endpoint</strong>.</li>
                                <li>Copy <strong>your venue's unique webhook URL</strong> below and paste it into the URL field in PayMongo:
                                    <div class="my-2">
                                        <div class="input-group input-group-sm">
                                            <input type="text" readonly class="form-control font-monospace"
                                                   id="pmWebhookUrl"
                                                   value="{{ url('/api/v1/webhooks/paymongo/' . $tenant->ensureWebhookToken()) }}">
                                            <button class="btn btn-outline-secondary" type="button"
                                                    onclick="navigator.clipboard.writeText(document.getElementById('pmWebhookUrl').value); this.innerHTML='<i class=&quot;bi bi-check-lg&quot;></i> Copied'">
                                                <i class="bi bi-clipboard me-1"></i>Copy
                                            </button>
                                        </div>
                                    </div>
                                </li>
                                <li>PayMongo will ask which events you want to listen for. Tick these three:
                                    <ul class="font-monospace small">
                                        <li>payment.paid</li>
                                        <li>payment.failed</li>
                                        <li>checkout_session.payment.paid</li>
                                    </ul>
                                </li>
                                <li>Click Save. PayMongo will then show you a <strong>Webhook Secret</strong> (it starts with <code>whsk_…</code>). <strong>You'll only see it once</strong> — copy it right away.</li>
                                <li>Back in {{ config('app.name') }} (<strong>Settings → Payments</strong>), paste that into the PayMongo <em>Webhook secret</em> box.</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 5 --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <div class="badge bg-primary fs-6 rounded-circle" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center">5</div>
                        <div class="flex-grow-1">
                            <h6 class="fw-semibold mb-2">Pick what your customers see at checkout</h6>
                            <p class="small mb-2">Still on <strong>{{ config('app.name') }} → Settings → Payments</strong>, tick only the options you turned on back in Step 2 (GCash, QR Ph, etc). If something isn't turned on in PayMongo, leave it unticked here too — otherwise your customers will see "No payment methods available."</p>
                            <p class="small mb-0">Flip the <strong>Enabled</strong> switch ON, click <strong>Save</strong>, and you're ready to accept payments!</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-success border-0 shadow-sm">
                <h6 class="fw-semibold mb-2"><i class="bi bi-check-circle me-2"></i>Verify with a live ₱20 payment</h6>
                <p class="small mb-2">Head to <a href="{{ route('customer.wallet.index') }}">your customer wallet</a> and run a small live top-up (₱20–50) using your own GCash account. Confirm:</p>
                <ul class="small mb-0">
                    <li>The checkout opens directly on the method you picked (no "no methods available" error).</li>
                    <li>Your wallet balance increases when you return to {{ config('app.name') }}.</li>
                    <li>The payment appears in your PayMongo dashboard under <strong>Payments</strong> within a few seconds.</li>
                </ul>
                <p class="small text-muted mt-2 mb-0">You can refund this verification payment to yourself from the PayMongo dashboard if you'd like — Payments → click the payment → Refund.</p>
            </div>

        </div>

        {{-- ─────────────────── Stripe ─────────────────── --}}
        <div x-show="provider === 'stripe'" x-cloak>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">Stripe Setup — Step by Step</h5>
                    <p class="small text-muted mb-0">Follow these 4 steps to accept real payments through Stripe. For most businesses, Stripe is ready in minutes after you finish their <em>Activate Payments</em> form; a few may need extra documents.</p>
                </div>
            </div>

            {{-- Step 1 --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <div class="badge bg-primary fs-6 rounded-circle" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center">1</div>
                        <div class="flex-grow-1">
                            <h6 class="fw-semibold mb-2">Sign up and verify your business</h6>
                            <ol class="small mb-2">
                                <li>Go to <a href="https://dashboard.stripe.com/register" target="_blank" rel="noopener">dashboard.stripe.com/register <i class="bi bi-box-arrow-up-right"></i></a>, create an account, and confirm your email.</li>
                                <li>Click <strong>Activate Payments</strong> on the dashboard and fill in:
                                    <ul>
                                        <li>Your business type, name, address, and tax ID (TIN for the Philippines)</li>
                                        <li>The owner's ID and date of birth</li>
                                        <li>Your bank account — this is where Stripe will send your earnings</li>
                                    </ul>
                                </li>
                                <li>Wait for the "Live payments are ready" confirmation. For most businesses this is instant; sometimes Stripe asks for extra documents.</li>
                            </ol>
                            <div class="alert alert-warning small mb-0">
                                You can't accept real payments until <em>Activate Payments</em> is finished. Don't skip this step.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 2 --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <div class="badge bg-primary fs-6 rounded-circle" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center">2</div>
                        <div class="flex-grow-1">
                            <h6 class="fw-semibold mb-2">Copy your Secret Key into {{ config('app.name') }}</h6>
                            <ol class="small mb-2">
                                <li>Go to <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener">dashboard.stripe.com/apikeys <i class="bi bi-box-arrow-up-right"></i></a>.</li>
                                <li>Make sure the top-right switch is on <strong>Live</strong>.</li>
                                <li>Under <em>Standard keys</em>, click <strong>Reveal</strong> next to the Secret key. It must start with <code>sk_live_…</code>.</li>
                                <li>Open another tab and go to <strong>{{ config('app.name') }} → Settings → Payments</strong>. Paste the key into the Stripe <em>Secret key</em> box.</li>
                            </ol>
                            <div class="alert alert-danger small mb-0">
                                <strong>Treat your Secret Key like a password.</strong> Don't email it, post it in chat, or share it in screenshots. We store it safely (encrypted) on our side.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 3: Webhook --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <div class="badge bg-primary fs-6 rounded-circle" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center">3</div>
                        <div class="flex-grow-1">
                            <h6 class="fw-semibold mb-2">Connect Stripe to {{ config('app.name') }} (webhook)</h6>
                            <p class="small text-muted">This is what lets Stripe tell {{ config('app.name') }} the moment a customer pays. Without it, wallets and bookings won't update automatically.</p>
                            <ol class="small">
                                <li>In your Stripe dashboard, make sure you're in <strong>Live</strong> mode, then click <strong>Developers → Webhooks → Add endpoint</strong>.</li>
                                <li>Copy <strong>your venue's unique webhook URL</strong> below and paste it into the endpoint URL field in Stripe:
                                    <div class="my-2">
                                        <div class="input-group input-group-sm">
                                            <input type="text" readonly class="form-control font-monospace"
                                                   id="spWebhookUrl"
                                                   value="{{ url('/api/v1/webhooks/stripe/' . $tenant->ensureWebhookToken()) }}">
                                            <button class="btn btn-outline-secondary" type="button"
                                                    onclick="navigator.clipboard.writeText(document.getElementById('spWebhookUrl').value); this.innerHTML='<i class=&quot;bi bi-check-lg&quot;></i> Copied'">
                                                <i class="bi bi-clipboard me-1"></i>Copy
                                            </button>
                                        </div>
                                    </div>
                                </li>
                                <li>Under <strong>Select events to listen to</strong>, tick these four:
                                    <ul class="font-monospace small">
                                        <li>checkout.session.completed</li>
                                        <li>payment_intent.succeeded</li>
                                        <li>payment_intent.payment_failed</li>
                                        <li>charge.refunded</li>
                                    </ul>
                                </li>
                                <li>Click <strong>Add endpoint</strong>. On the next page, click <strong>Reveal</strong> next to <em>Signing secret</em> — it starts with <code>whsec_…</code>.</li>
                                <li>Back in {{ config('app.name') }} (<strong>Settings → Payments</strong>), paste that into the Stripe <em>Webhook secret</em> box.</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 4 --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <div class="badge bg-primary fs-6 rounded-circle" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center">4</div>
                        <div class="flex-grow-1">
                            <h6 class="fw-semibold mb-2">Turn Stripe on in {{ config('app.name') }}</h6>
                            <p class="small mb-0">Open <strong>{{ config('app.name') }} → Settings → Payments</strong>, flip the <strong>Stripe Enabled</strong> switch ON, and click Save. Your customers can now pay by card!</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-success border-0 shadow-sm">
                <h6 class="fw-semibold mb-2"><i class="bi bi-check-circle me-2"></i>Verify with a live charge</h6>
                <p class="small mb-2">Head to <a href="{{ route('customer.wallet.index') }}">your customer wallet</a> and run a small live top-up (~$1) with your own card. Confirm:</p>
                <ul class="small mb-0">
                    <li>The Stripe checkout opens and accepts your card.</li>
                    <li>Your wallet balance increases when you return to {{ config('app.name') }}.</li>
                    <li>The charge appears in your Stripe dashboard under <strong>Payments</strong>.</li>
                </ul>
                <p class="small text-muted mt-2 mb-0">You can refund this verification charge from the Stripe dashboard (Payments → click the payment → Refund) — Stripe returns the gateway fee on refunds within 90 days.</p>
            </div>

        </div>

        {{-- ─────────────────── Common questions ─────────────────── --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-chat-left-dots me-2 text-primary"></i>Common Questions</h6>
                <small class="text-muted">Click any question to see the answer.</small>
            </div>
            <div class="card-body">
                <div class="accordion accordion-flush" id="troubleshootAccordion">

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#q1">
                                <i class="bi bi-question-circle me-2 text-warning"></i>My customer is told "No payment methods available" — what's wrong?
                            </button>
                        </h2>
                        <div id="q1" class="accordion-collapse collapse" data-bs-parent="#troubleshootAccordion">
                            <div class="accordion-body small">
                                <p class="mb-2">Don't worry — this is the most common hiccup and it's a quick fix.</p>
                                <p class="mb-2">It means {{ config('app.name') }} is offering a payment option (like GCash or Cards) that isn't turned on inside your PayMongo or Stripe dashboard.</p>
                                <p class="mb-0"><strong>Quick fix:</strong> open your PayMongo / Stripe dashboard and turn on that option, <em>or</em> untick it in <strong>{{ config('app.name') }} → Settings → Payments</strong>. Either way, just keep both sides matching.</p>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#q2">
                                <i class="bi bi-question-circle me-2 text-warning"></i>My customer paid but their wallet didn't update — what should I do?
                            </button>
                        </h2>
                        <div id="q2" class="accordion-collapse collapse" data-bs-parent="#troubleshootAccordion">
                            <div class="accordion-body small">
                                <p class="mb-2">First, take a breath — the money is safe. PayMongo or Stripe is holding it for you whether or not {{ config('app.name') }} has caught up yet.</p>
                                <p class="mb-2"><strong>Try these in order:</strong></p>
                                <ol class="mb-2">
                                    <li>Ask your customer to refresh their wallet page. We usually catch up automatically when they return from the payment screen.</li>
                                    <li>Still nothing? Make sure you finished <strong>Step 4 (the webhook setup)</strong>. The webhook is what tells us a payment went through, even if your customer closed their browser before returning.</li>
                                    <li>If you set up the webhook and it's still not working, check that your venue's website address (the URL you pasted into PayMongo / Stripe) is correct and reachable on the internet.</li>
                                </ol>
                                <p class="mb-0">If you've tried all of these, the payment is still in PayMongo / Stripe — you can credit your customer's wallet manually from the admin side, and we'll help you trace what went wrong.</p>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#q3">
                                <i class="bi bi-question-circle me-2 text-warning"></i>I think the webhook isn't working. How do I fix it?
                            </button>
                        </h2>
                        <div id="q3" class="accordion-collapse collapse" data-bs-parent="#troubleshootAccordion">
                            <div class="accordion-body small">
                                <p class="mb-2">The most common reason is a Webhook Secret mismatch — the secret saved in {{ config('app.name') }} doesn't match the one PayMongo or Stripe is currently using.</p>
                                <p class="mb-2"><strong>Easiest fix — start fresh:</strong></p>
                                <ol class="mb-0">
                                    <li>In your PayMongo or Stripe dashboard, delete the existing webhook.</li>
                                    <li>Create a new one (use the same URL — copy it again from Settings → Payments to be safe).</li>
                                    <li>Copy the fresh Webhook Secret it shows you (starts with <code>whsk_…</code> for PayMongo, <code>whsec_…</code> for Stripe).</li>
                                    <li>Paste it into <strong>{{ config('app.name') }} → Settings → Payments</strong> and click Save.</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#q4">
                                <i class="bi bi-question-circle me-2 text-info"></i>Can I practice with fake money before accepting real payments?
                            </button>
                        </h2>
                        <div id="q4" class="accordion-collapse collapse" data-bs-parent="#troubleshootAccordion">
                            <div class="accordion-body small">
                                <p class="mb-2"><strong>Yes — and we recommend it!</strong></p>
                                <p class="mb-2">Both PayMongo and Stripe have a <strong>Test Mode</strong> where you can run pretend payments. Real money is never charged.</p>
                                <p class="mb-2">Just switch the mode toggle in their dashboard from Live to <em>Test</em> and follow the same setup steps. The Test keys will start with <code>sk_test_…</code> instead of <code>sk_live_…</code>.</p>
                                <p class="mb-0">When you're ready for real customers, switch back to Live mode in their dashboard and paste the new Live keys + webhook secret into {{ config('app.name') }} — they're different from the Test ones, so you can't reuse them.</p>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#q5">
                                <i class="bi bi-question-circle me-2 text-success"></i>Does {{ config('app.name') }} take a cut of my payments?
                            </button>
                        </h2>
                        <div id="q5" class="accordion-collapse collapse" data-bs-parent="#troubleshootAccordion">
                            <div class="accordion-body small">
                                <p class="mb-2"><strong>No. Your customers' payments go straight to you.</strong></p>
                                <p class="mb-2">All payments are deposited into <strong>your own</strong> PayMongo or Stripe account and from there to your bank. {{ config('app.name') }} never holds, routes, or takes any of the money.</p>
                                <p class="mb-0">The only fees you'll ever see are the standard ones PayMongo or Stripe charges (for example, around 2.5% + ₱15 per GCash payment). Those go to them, not to us.</p>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#q6">
                                <i class="bi bi-question-circle me-2 text-success"></i>How long until I receive the money in my bank account?
                            </button>
                        </h2>
                        <div id="q6" class="accordion-collapse collapse" data-bs-parent="#troubleshootAccordion">
                            <div class="accordion-body small">
                                <p class="mb-2">That's set by PayMongo or Stripe directly — {{ config('app.name') }} isn't involved.</p>
                                <ul class="mb-0">
                                    <li><strong>PayMongo</strong> typically deposits to your bank 1–3 business days after payment, depending on your settlement schedule.</li>
                                    <li><strong>Stripe</strong> usually pays out on a rolling 2-day schedule for most countries.</li>
                                </ul>
                                <p class="mb-0 mt-2">You can see exact payout dates inside your PayMongo or Stripe dashboard under "Payouts" or "Balances."</p>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#q7">
                                <i class="bi bi-question-circle me-2 text-success"></i>How do I refund a customer?
                            </button>
                        </h2>
                        <div id="q7" class="accordion-collapse collapse" data-bs-parent="#troubleshootAccordion">
                            <div class="accordion-body small">
                                <p class="mb-2">For online payments, the easiest way is straight from your PayMongo or Stripe dashboard:</p>
                                <ol class="mb-2">
                                    <li>Go to <strong>Payments</strong> in your PayMongo / Stripe dashboard.</li>
                                    <li>Find the payment and click <strong>Refund</strong>.</li>
                                    <li>The money goes back to the customer's original payment method (1–10 business days, depending on the method).</li>
                                </ol>
                                <p class="mb-0">If the customer paid using their {{ config('app.name') }} wallet, you can also issue a wallet credit directly from <strong>Admin → Customers</strong>.</p>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#q8">
                                <i class="bi bi-question-circle me-2 text-danger"></i>I lost my Secret Key or Webhook Secret. What now?
                            </button>
                        </h2>
                        <div id="q8" class="accordion-collapse collapse" data-bs-parent="#troubleshootAccordion">
                            <div class="accordion-body small">
                                <p class="mb-2">No problem — you can always make a new one.</p>
                                <ul class="mb-2">
                                    <li><strong>Lost Secret Key:</strong> in your PayMongo / Stripe dashboard, go to <em>Developers → API Keys</em>, generate a new Secret Key, and paste it into Settings → Payments.</li>
                                    <li><strong>Lost Webhook Secret:</strong> delete the existing webhook in PayMongo / Stripe, create a new one (re-paste the same webhook URL), and copy the new secret into Settings → Payments.</li>
                                </ul>
                                <p class="mb-0">If you lose your dashboard login itself, use the "Forgot password?" link on PayMongo or Stripe's sign-in page — they handle account recovery, not us.</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- Still stuck CTA --}}
        <div class="alert alert-light border shadow-sm mb-5 text-center">
            <i class="bi bi-life-preserver me-2 text-primary"></i>
            <strong>Still stuck?</strong> Reach out to your {{ config('app.name') }} support contact and we'll walk you through it.
        </div>

        <div class="text-center mt-4 mb-5">
            <a href="{{ route('admin.settings.index') }}#gateways" class="btn btn-primary">
                <i class="bi bi-gear me-1"></i>Go to Payments Settings
            </a>
        </div>

    </div>
</div>

@endsection
