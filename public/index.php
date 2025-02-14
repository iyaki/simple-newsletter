<!DOCTYPE html>
<html lang="en">
<head>
	<title>Simple Newsletter</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="author" content="Ivan Yaki">
	<meta name="description" content="Simple and free Atom and RSS feeds to newsletter subscription service">
	<meta property="og:title" content="Simple Newsletter">
	<meta property="og:description" content="Free Atom and RSS feeds to newsletter subscription service">
	<meta property="og:url" content="https://simple-newsletter.com/">
	<meta property="og:site_name" content="Simple Newsletter">

	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/water.css">

	<link
		rel="icon"
		href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📨</text></svg>"
	/>
	<style>
		:target {
			scroll-margin-block: 5ex;
		}
		section {
			margin: 2.5em auto;
		}
	</style>
</head>
<body>
	<main>
		<h1 style="text-align: center; margin-top: 1em; margin-bottom: 2em;">Simple Newsletter</h1>
		<h2 style="text-align: center;">Atom & RSS Feeds to Newsletter</h2>
		<h3 style="text-align: center;">The news that you want to hear about, delivered directly to your e-mail inbox, for free.</h3>
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
				<input type="hidden" name="return" value="https://simple-newsletter.com/">
				<fieldset>
					<div>
						<label>
							Feed
							<input type="url" name="uri" required>
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
		<section id="about" style="position: relative;">
			<header>
				<h2>Why?</h2>
			</header>
			<p>I have used RSS Feeds for a long time and I completely love them. I use them, advocate for them and try to spread the word about them.</p>
			<p>But at the beggining of 2024 I came across <a href="https://ochagavia.nl/blog/rss-is-dead-subscribe-through-email/" title="RSS is dead, subscribe through email. - Adolfo Ochagavía">this blogpost</a> and it left me thinking.</p>
			<p>It's true that for a lot of readers from newer generations the RSS is an old and strange technology. But for publishers the RSS (or Atom) is almost omnipresent without efforts, unlike email newsletters.</p>
			<p>So, inspired by the great <a href="https://kill-the-newsletter.com/"><i>Kill the Newsletter</i></a>, I decided to build this service that converts any Atom or RSS feed into a newsletter, accessible via email for any reader.</p>
		</section>
		<section id="faq">
			<header>
				<h2>F.A.Q.</h2>
				<ul style="list-style: none; padding: 0;">
					<li><details>
						<summary>How do I subscribe?</summary>
						<p>Just input the Atom or RSS feed’s URI and your email address in the form and hit submit. You’ll receive and email to confirm the subscription.</p>
					</details></li>
					<li><details>
						<summary>How do I unsubscribe?</summary>
						<p>Each newsletter includes an unsubscribe link, giving you the freedom to opt-out anytime.	</p>
					</details></li>
					<li><details>
						<summary>Is this service free?</summary>
						<p>Yes!</p>
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
			<a href="https://editor.swagger.io/?url=https://simple-newsletter.com/api-spec.yaml">OpenAPI API Docs</a>
			<h3>Example HTML Form for publishers</h3>
			<pre><code><?= htmlentities(<<<HTML
			<form action="https://simple-newsletter.com/v1/subscriptions/">
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
	<footer style="text-align: center; display: flex; justify-content: center; align-items: stretch; gap: 1em; padding-top: 20px;">
		<p style="margin: 0">Made with 🧉 by <a href="https://iyaki.ar">iyaki</a></p>
		<div> - </div>
		<a href="https://github.com/iyaki/simple-newsletter" title="Simple Newsletter on Github"><svg viewBox="0 0 32.58 31.77" height="1.5em"><path d="M16.29.0C7.29.0.0 7.29.0 16.29c0 7.2 4.67 13.3 11.14 15.46.81.15 1.11-.35 1.11-.79.0-.39-.01-1.41-.02-2.77-4.53.98-5.49-2.18-5.49-2.18-.74-1.88-1.81-2.38-1.81-2.38-1.48-1.01.11-.99.11-.99 1.63.12 2.5 1.68 2.5 1.68 1.45 2.49 3.81 1.77 4.74 1.35.15-1.05.57-1.77 1.03-2.18-3.62-.41-7.42-1.81-7.42-8.05.0-1.78.63-3.23 1.68-4.37-.17-.41-.73-2.07.16-4.31.0.0 1.37-.44 4.48 1.67 1.3-.36 2.69-.54 4.08-.55 1.38.0 2.78.19 4.08.55 3.11-2.11 4.48-1.67 4.48-1.67.89 2.24.33 3.9.16 4.31 1.04 1.14 1.67 2.59 1.67 4.37.0 6.26-3.81 7.63-7.44 8.04.58.5 1.11 1.5 1.11 3.02.0 2.18-.02 3.93-.02 4.47.0.44.29.94 1.12.78 6.47-2.16 11.13-8.26 11.13-15.45C32.58 7.29 25.29.0 16.29.0z"></path></svg>
		</a>
	</footer>
</body>
</html>
