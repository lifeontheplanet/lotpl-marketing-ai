Version 1.3.0

LOTPL Ladakh Blog Generator (OpenAI)

What changed
- Replaced the hard-coded Claude API call with OpenAI Responses API.
- Added structured JSON schema output for much more reliable generation.
- Added custom topic input and free-form related keyword input.
- Added retry handling, request lock, and last API log for debugging silent failures.
- Added settings page for API key, model, web search, timeout, default word count, and default WP category.
- Keeps auto slug, draft status, Rank Math focus keyword/description, and image suggestions.

Install
1. Upload the ZIP in WordPress Plugins > Add New > Upload Plugin.
2. Activate it.
3. Go to Blog Generator > Settings.
4. Paste a fresh OpenAI API key.
5. Save settings.
6. Open Blog Generator and generate a draft.

Notes
- Recommended model: gpt-5.4-mini for cost/performance balance.
- Higher-quality option: gpt-5.4.
- Built-in web search can be turned off in Settings if you want cheaper or more stable first-pass generation.
- The old hard-coded Anthropic key from the pasted snippet should be treated as exposed.
