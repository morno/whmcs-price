# Security Policy

## Supported Versions

The following versions of Mornolink for WHMCS currently receive security
updates. We recommend always running the latest release.

| Version | Supported          |
| ------- | ------------------ |
| 2.8.x   | :white_check_mark: |
| 2.7.x   | :white_check_mark: |
| < 2.6   | :x:                |

## Reporting a Vulnerability

If you discover a security vulnerability in Mornolink for WHMCS, please
report it privately — **do not** open a public GitHub issue, as that would
expose the vulnerability to other users before a fix is available.

### How to report

**Preferred:** Use GitHub's private vulnerability reporting feature by
navigating to the **Security** tab of this repository and clicking
**"Report a vulnerability"**.

**Alternative:** Email mornolink@outlook.com with the subject line
`[SECURITY] Mornolink for WHMCS`. Please include:

- A description of the vulnerability and its potential impact
- Steps to reproduce (proof-of-concept code, screenshots, or a video if
  helpful)
- The affected version(s) of the plugin
- Your WordPress, PHP, and WHMCS versions
- Any suggested mitigation, if you have one

### What to expect

- **Acknowledgement:** within 72 hours of your report
- **Initial assessment:** within 7 days, confirming whether the report is
  accepted as a valid vulnerability
- **Status updates:** at least every 14 days while the issue is being
  worked on
- **Fix and disclosure:** confirmed vulnerabilities are typically patched
  within 30–90 days depending on severity and complexity

If a report is **accepted**, we will:
1. Develop and test a fix in a private branch
2. Release a patched version
3. Publish a security advisory on GitHub crediting the reporter (unless
   anonymity is requested)

If a report is **declined** (e.g., not actually a security issue, already
known, or out of scope), we will explain why.

### Scope

In scope:
- The plugin's PHP code (Gutenberg, Elementor, and Divi 5 integrations)
- Shortcode handling and input sanitization
- Settings page and admin functionality
- Data fetched from and sent to WHMCS

Out of scope:
- Vulnerabilities in WordPress core, WHMCS itself, or third-party page
  builders (please report those to their respective projects)
- Issues requiring administrator-level access that the user already has
- Social engineering of the plugin's maintainers

### Safe harbor

We will not pursue legal action against researchers who:
- Make a good-faith effort to comply with this policy
- Avoid privacy violations, data destruction, or service disruption
- Give us reasonable time to fix the issue before public disclosure

Thank you for helping keep Mornolink for WHMCS and its users safe.
