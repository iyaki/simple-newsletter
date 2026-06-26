<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Simple Newsletter - Free RSS & Atom Feed to Email Newsletter</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="Ivan Yaki">
    <meta name="description" content="Transform any RSS or Atom feed into an email newsletter. Free, privacy-friendly, double opt-in. No RSS reader needed.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= rtrim(\getenv('URI_SELF'), '/') ?>">
    <meta property="og:title" content="Simple Newsletter - RSS & Atom to Email">
    <meta property="og:description" content="Convert RSS or Atom feeds into email newsletters. Free, privacy-friendly, double opt-in. No account required.">
    <meta property="og:url" content="<?= \getenv('URI_SELF') ?>">
    <meta property="og:site_name" content="Simple Newsletter">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="en_US">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="Simple Newsletter - RSS & Atom to Email">
    <meta name="twitter:description" content="Convert RSS or Atom feeds into email newsletters. Free, privacy-friendly, double opt-in. No account required.">

    <link rel="preload" href="https://cdn.jsdelivr.net/npm/water.css@2/out/water.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/water.css"></noscript>

    <link
        rel="icon"
        href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📨</text></svg>"
    />
    <style>
        :target {
            scroll-margin-block: 5ex;
        }
        .skip-link {
            position: absolute;
            left: -9999px;
            top: auto;
        }
        .skip-link:focus {
            position: static;
            left: 0;
        }
        section {
            margin: 2.5em auto;
        }
    </style>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "Simple Newsletter",
        "description": "Free service that converts RSS and Atom feeds into email newsletters",
        "url": "<?= rtrim(\getenv('URI_SELF'), '/') ?>",
        "applicationCategory": "Utility",
        "operatingSystem": "Web",
        "offers": { "@type": "Offer", "price": "0", "priceCurrency": "USD" },
        "isAccessibleForFree": true,
        "featureList": "RSS to email, Atom feed newsletter, Double opt-in, Privacy-friendly, No account required"
    }
    </script>
</head>
<body>
    <a href="#main" class="skip-link">Skip to main content</a>
    <main id="main">
        <h1 style="text-align: center; margin-top: 1em; margin-bottom: 2em;">Simple Newsletter</h1>
        <h2 style="text-align: center;">RSS & Atom Feed to Email Newsletter</h2>
        <section id="intro" style="max-width: 750px; margin: 0.5em auto 3em; padding: 0 1em;">
            <p style="text-align: center; font-size: 1.15em; line-height: 1.65; margin: 0;">Simple Newsletter is a free web service that converts any RSS or Atom feed into email newsletters. Readers subscribe with their email, confirm via double opt-in, and receive new posts directly in their inbox — no RSS reader app required. Publishers get automatic email distribution without managing a mailing list.</p>
        </section>
        <section style="margin: 7em auto 10em;">
            <p>Enter the feed’s URI and your email to transform any Atom or RSS feed into a newsletter.</p>
            <style>
                .subscription-form {
                    margin: 2em auto;
                    text-align: center;
                }
                .subscription-form fieldset {
                    padding-top: 2em;
                    padding-bottom: 2em;
                }
                .subscription-form fieldset label {
                    text-align: left;
                    margin-left: 1em;
                    margin-right: 1em;
                    margin-bottom: 2em;
                }
                .subscription-form fieldset button[type="submit"] {
                    background-color: var(--links);
                    color: white;
                    padding-left: 3em;
                    padding-right: 3em;
                }
            </style>
            <form action="/v1/subscriptions/" class="subscription-form">
                <input type="hidden" name="return" value="<?= \getenv('URI_SELF') ?>">
                <fieldset>
                    <div>
                        <label>
                            Feed
                            <input type="url" name="uri" value="<?= \is_string($_GET['feed'] ?? null)
                                ? $_GET['feed']
                                : ''; ?>" required>
                        </label>
                        <label>
                            Email
                            <input type="email" name="email" required>
                        </label>
                    </div>
                    <button type="submit">Subscribe!</button>
                </fieldset>
            </form>
        </section>
        <section id="how-it-works">
            <h2>How It Works</h2>
            <ol>
                <li><strong>Enter a feed and email</strong> — Paste any RSS or Atom feed URL (e.g., <code>https://example.com/blog/feed.xml</code> or a YouTube channel RSS)</li>
                <li><strong>Confirm your email</strong> — Click the double opt-in link you receive (sent immediately, takes 30 seconds)</li>
                <li><strong>Receive newsletters</strong> — New posts from the feed arrive in your inbox within an hour of publication</li>
                <li><strong>Unsubscribe anytime</strong> — One-click link in every email, instant removal</li>
            </ol>
        </section>
        <section id="about" style="position: relative;">
            <header>
                <h2>Why RSS to Email?</h2>
            </header>
            <p>I have used RSS feeds for a long time and I completely love them. I use them, advocate for them and try to spread the word about them.</p>
            <p>But at the beginning of 2024 I came across <a href="https://ochagavia.nl/blog/rss-is-dead-subscribe-through-email/" title="RSS is dead, subscribe through email. - Adolfo Ochagavía">this blogpost</a> and it left me thinking.</p>
            <p>It's true that for a lot of readers from newer generations RSS feeds can feel unfamiliar. But for publishers, RSS and Atom feeds are almost omnipresent without extra effort — unlike email newsletters that require a mailing list service or platform like Substack, ConvertKit, or Mailchimp.</p>
            <p>So I built this service that converts any Atom or RSS feed into a newsletter, accessible via email for any reader — no feed reader needed, no newsletter platform to manage, and no subscriber list to maintain.</p>
        </section>
        <section id="faq">
            <header>
                <h2>Frequently Asked Questions</h2>
                <ul style="list-style: none; padding: 0;">
                    <li><details>
                        <summary>How do I subscribe?</summary>
                        <p>Just input the Atom or RSS feed's URI and your email address in the form and hit submit. You'll receive an email to confirm the subscription.</p>
                    </details></li>
                    <li><details>
                        <summary>How do I unsubscribe?</summary>
                        <p>Each newsletter includes an unsubscribe link, giving you the freedom to opt-out anytime.</p>
                    </details></li>
                    <li><details>
                        <summary>Is this service free?</summary>
                        <p>Yes, Simple Newsletter is completely free. No account required, no premium tiers.</p>
                    </details></li>
                    <li><details>
                        <summary>What feed formats are supported?</summary>
                        <p>Any RSS 2.0 or Atom 1.0 feed. This includes most blogs (WordPress, Ghost, Medium exports), YouTube channel RSS, podcast feeds, and most news sites.</p>
                    </details></li>
                    <li><details>
                        <summary>How often are newsletters sent?</summary>
                        <p>New posts are delivered once per day for every source.</p>
                    </details></li>
                    <li><details>
                        <summary>What data do you store?</summary>
                        <p>We store your email address, the feed URI you subscribed to, and a confirmation timestamp. No browsing data, no cookies for tracking, no selling to third parties. Delete your subscription anytime via the unsubscribe link — data is removed immediately.</p>
                    </details></li>
                    <li><details>
                        <summary>I’m a publisher, can I integrate this service into my website?</summary>
                        <p>Definitely! Check out our <a href="#docs">documentation</a> for easy integration instructions.</p>
                    </details></li>
                    <li><details>
                        <summary>Something is wrong</summary>
                        <p>Please, <a href="mailto:simple-newsletter@iyaki.ar">let me know</a></p>
                    </details></li>
                </ul>
            </header>
        </section>
        <section id="docs">
            <header>
                <h2>API Docs</h2>
            </header>
            <p><strong>For publishers:</strong> Add email newsletters to your site in 2 minutes. No migration from RSS, no newsletter platform to manage, no subscriber database to maintain.</p>
            <ul>
                <li>Works with any RSS 2.0 or Atom 1.0 feed — blogs, YouTube channels, podcasts, news sites</li>
                <li>Double opt-in compliance</li>
                <li>Automatic delivery within the day of publication</li>
                <li>Free, no premium tiers, no account required</li>
                <li>Unsubscribe link in every email</li>
            </ul>
            <a href="https://editor.swagger.io/?url=https://simple-newsletter.com/api-spec.yaml">OpenAPI API Docs</a>
            <h3>Example HTML Form for publishers</h3>
            <pre><code><?= htmlentities(<<<HTML
                <form action="<?= \getenv('URI_SELF') ?>/v1/subscriptions/">
                    <input type="hidden" name="uri" value="https://your-domain.com/path/to/feed.xml">
                    <input type="hidden" name="return" value="https://your-domain.com/">
                    <label>
                        Email
                        <input type="email" name="email" required>
                    </label>
                    <button type="submit">Subscribe!</button>
                </form>
                HTML) ?></code></pre>
            <details>
                <summary>Known limitations</summary>
                <ul>
                    <li>Currently the service sends only one newsletter per day for each subscription (Feed/E-mail combination). I'm working to improve that.</li>
                    <!-- <li>For performance reasons all the subscriptors to a Feed are BCCed on the same e-mail. If you don't find the newsletter e-mails in your inbox please check your spam folder and mark the message as "Not Spam".</li> -->
                </ul>
            </details>
        </section>
    </main>
    <footer style="text-align: center; padding-top: 20px;">
        <nav style="margin-bottom: 1em;">
            <a href="#how-it-works">How It Works</a> ·
            <a href="#about">Why RSS to Email</a> ·
            <a href="#faq">FAQ</a> ·
            <a href="#docs">API Docs</a> ·
            <a href="https://github.com/iyaki/simple-newsletter" title="Simple Newsletter on GitHub">Source Code</a>
        </nav>
        <p style="margin: 0">Made with 🧉 by <a href="https://iyaki.ar">iyaki</a></p>
    </footer>
    <script data-goatcounter="https://simple-newsletter.goatcounter.com/count" async src="//gc.zgo.at/count.js"></script>
</body>
</html>
