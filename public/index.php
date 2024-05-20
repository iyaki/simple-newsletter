<!DOCTYPE html>
<html lang="en">
<head>
	<title>Simple Newsletter</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="author" content="Ivan Yaki">
	<meta name="description" content="Simple Atom and RSS feeds to newsletter subscription service">
	<meta property="og:title" content="Simple Newsletter">

	<!--
		`og` stands for Open Graph, which is a protocol for how your website appears when linked to from another site. These tags are critical to ensuring your site gains an appropriate card when sharing on social media.

		TODO
	-->
	<meta property="og:description" content="Simple Atom and RSS feeds to newsletter subscription service">
	<!-- <meta property="og:image" content="/some-image.png"> -->
	<meta property="og:url" content="/">
	<meta property="og:site_name" content="Simple Newsletter">

	<!--
		`twitter` is similar to Open Graph, but specific to Twitter. There are multiple card types you can choose from.

		Learn more on Twitter's documentation:
		https://developer.twitter.com/en/docs/tweets/optimize-with-cards/guides/getting-started

		TODO
	-->
	<!-- <meta name="twitter:card" content="summary_large_image"> -->
	<!-- <meta name="twitter:image:alt" content="image description"> -->

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
		<h3 style="text-align: center;">The news that you want to hear about, delivered directly to your inbox</h3>
		<section style="margin: 7em auto 10em;">
			<p>Enter the feed’s URI and your email to transform any Atom or RSS feed into a personalized newsletter.</p>
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
					padding-left: 3em;
					padding-right: 3em;
				}
			</style>
			<form action="/v1/subscriptions/" class="subscription-form">
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
			<p>But beggining the 2024 I came across <a href="https://ochagavia.nl/blog/rss-is-dead-subscribe-through-email/" title="RSS is dead, subscribe through email. - Adolfo Ochagavía">this post</a> and it left me thinking.</p>
			<p>It's true that for a lot of the readers of new generations the RSS is an old and strange technology that they will never use. But for the publishers the RSS (or Atom) is almost omnipresent without any effort, unlike newsletters.</p>
			<p>So, inspired by the great <a href="https://kill-the-newsletter.com/"><i>Kill the Newsletter</i></a>, I decided to build this service that converts any Atom or RSS feed into a newsletter, accessible via email for any reader.</p>
		</section>
		<section id="faq">
			<header>
				<h2>F.A.Q.</h2>
				<ul style="list-style: none; padding: 0;">
					<li><details>
						<summary>How do I subscribe?</summary>
						<p>Just input the Atom or RSS feed’s URI and your email address in the form and hit submit. You’ll receive a confirmation email to finalize your subscription.</p>
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
				<input type="hidden" name="return" value="https://your-domain.com/thanks-for-subscribing.html">
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
					<li>Currently the service sends only one newsletter by day for a single subscription (Feed/E-mail combination). I'm working to improve that.</li>
					<!-- <li>For performance reasons all the subscriptors to a Feed are BCCed on the same e-mail. If you don't find the newsletter e-mails in your inbox please check your spam folder and mark the message as "Not Spam".</li> -->
				</ul>
			</details>
		</section>
	</main>
	<footer style="text-align: center;">
		<p>Made with 🧉 by <a href="https://iyaki.ar">iyaki</a></p>
	</footer>
</body>
</html>
