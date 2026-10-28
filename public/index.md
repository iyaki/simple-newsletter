# Simple Newsletter — RSS & Atom Feed to Email Newsletter

Simple Newsletter is a free web service that converts any RSS or Atom feed into email newsletters. Readers subscribe with their email, confirm via double opt-in, and receive new posts directly in their inbox — no RSS reader app required. Publishers get automatic email distribution without managing a mailing list.

## Subscribe

Submit a feed URL and email address to `GET/POST /v1/subscriptions/?uri=<feed-url>&email=<address>` (double opt-in confirmation email is sent immediately).

## How It Works

1. **Enter a feed and email** — Paste any RSS or Atom feed URL (e.g., `https://example.com/blog/feed.xml` or a YouTube channel RSS)
2. **Confirm your email** — Click the double opt-in link you receive (sent immediately, takes 30 seconds)
3. **Receive newsletters** — New posts from the feed arrive in your inbox once per day, bundled into a single digest email
4. **Unsubscribe anytime** — One-click link in every email, instant removal

## Why RSS to Email?

I have used RSS feeds for a long time and I completely love them. I use them, advocate for them and try to spread the word about them.

But at the beginning of 2024 I came across [this blogpost](https://ochagavia.nl/blog/rss-is-dead-subscribe-through-email/) ("RSS is dead, subscribe through email." by Adolfo Ochagavía) and it left me thinking.

It's true that for a lot of readers from newer generations RSS feeds can feel unfamiliar. But for publishers, RSS and Atom feeds are almost omnipresent without extra effort — unlike email newsletters that require a mailing list service or platform like Substack, ConvertKit, or Mailchimp.

So I built this service that converts any Atom or RSS feed into a newsletter, accessible via email for any reader — no feed reader needed, no newsletter platform to manage, and no subscriber list to maintain.

## Frequently Asked Questions

### How do I subscribe?

Just input the Atom or RSS feed's URI and your email address in the form and hit submit. You'll receive an email to confirm the subscription.

### How do I unsubscribe?

Each newsletter includes an unsubscribe link, giving you the freedom to opt-out anytime.

### Is this service free?

Yes, Simple Newsletter is completely free. No account required, no premium tiers.

### What feed formats are supported?

Any RSS 2.0 or Atom 1.0 feed. This includes most blogs (WordPress, Ghost, Medium exports), YouTube channel RSS, podcast feeds, and most news sites.

### How often are newsletters sent?

New posts are delivered once per day per feed, bundled into a single digest email.

### What data do you store?

We store your email address, the feed URI you subscribed to, and a confirmation timestamp. No browsing data, no cookies for tracking, no selling to third parties. Delete your subscription anytime via the unsubscribe link — data is removed immediately. See the [Privacy Policy](/privacy/).

### I'm a publisher, can I integrate this service into my website?

Yes — see the API docs below.

### Why do links to my posts include utm parameters?

Links to your posts in the newsletters are tagged with `utm_source=simple-newsletter.com&utm_medium=email`, so your analytics can show how much traffic Simple Newsletter sends your way. You can filter or exclude it like any other email source.

### Something is wrong

Please [let me know](mailto:simple-newsletter@iyaki.ar).

## API Docs

**For publishers:** Add email newsletters to your site in 2 minutes. No migration from RSS, no newsletter platform to manage, no subscriber database to maintain.

- Works with any RSS 2.0 or Atom 1.0 feed — blogs, YouTube channels, podcasts, news sites
- Double opt-in compliance
- Automatic delivery within the day of publication
- Free, no premium tiers, no account required
- Unsubscribe link in every email

OpenAPI docs: <https://editor.swagger.io/?url=https://simple-newsletter.com/api-spec.yaml> (spec at [/api-spec.yaml](/api-spec.yaml))

### Example HTML Form for publishers

```html
<form action="https://simple-newsletter.com/v1/subscriptions/">
    <input type="hidden" name="uri" value="https://your-domain.com/path/to/feed.xml">
    <input type="hidden" name="return" value="https://your-domain.com/">
    <label>
        Email
        <input type="email" name="email" required>
    </label>
    <button type="submit">Subscribe!</button>
</form>
```

## Links

- [How It Works](/#how-it-works) · [FAQ](/#faq) · [API Docs](/#docs)
- [Source Code](https://github.com/iyaki/simple-newsletter) · [Terms](/terms/) · [Privacy](/privacy/)
- [Support the project on GitHub Sponsors](https://github.com/sponsors/iyaki)

Made with 🧉 by [iyaki](https://iyaki.ar)
