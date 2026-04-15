# Contributing to MC-EMS Premium

Thank you for your interest in contributing to MC-EMS Premium! This document provides guidelines and instructions for contributing.

## Code of Conduct

We are committed to providing a welcoming and inclusive environment for all contributors. Please be respectful and professional in all interactions.

## Getting Started

1. **Fork the repository** on GitHub
2. **Clone your fork** locally
   ```bash
   git clone https://github.com/YOUR-USERNAME/mc-ems-premium.git
   cd mc-ems-premium
   ```
3. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

## Development Setup

### Requirements
- WordPress 5.0+
- PHP 7.4+
- MC-EMS – Exam Center for Tutor LMS plugin

### Local Development
```bash
# Install dependencies (if using Composer)
composer install

# Run tests
composer test

# Code quality checks
composer lint
```

## Coding Standards

### PHP
- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- Use PHP 7.4+ type hints
- Always escape output with `esc_html()`, `esc_attr()`, `esc_url()`
- Sanitize input with `sanitize_text_field()`, `intval()`, etc.
- Check user capabilities with `current_user_can()`

### JavaScript
- Use modern ES6+ syntax
- Follow WordPress JavaScript coding standards
- No ES5 transpilation needed for WP 5.0+

### CSS
- Use BEM naming convention
- Mobile-first approach
- Namespace classes with plugin prefix (`.mcems-`)

### Commit Messages
```
type(scope): short description

Longer explanation if needed.

Fixes #issue-number
```

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`

## Pull Request Process

1. **Update documentation** - Update README.md if adding features
2. **Add changelog entry** - Update CHANGELOG.md
3. **Test thoroughly** - Verify functionality across WordPress versions
4. **Keep commits clean** - Squash if needed
5. **Write clear PR description**
   - Describe what changed and why
   - Reference related issues
   - Include screenshots if UI changes

## Reporting Bugs

### Security Issues
**Do not** create public issues for security vulnerabilities. Email: [security@example.com]

### General Bugs
1. Check if the bug is already reported in [Issues](https://github.com/Inamo87100/mc-ems-premium/issues)
2. Include:
   - WordPress version
   - PHP version
   - MC-EMS – Exam Center for Tutor LMS version
   - Step-by-step reproduction
   - Expected vs actual behavior
   - Screenshot if applicable

## Feature Requests

1. Check [Discussions](https://github.com/Inamo87100/mc-ems-premium/discussions) first
2. Explain the use case
3. Provide examples or mockups
4. Stay open to discussion

## Testing

Before submitting a PR, test with:
- WordPress 5.0, 5.1, 6.0, 6.4 (latest)
- PHP 7.4, 8.0, 8.1, 8.3 (latest)
- Different user roles (Admin, Editor, Author, Subscriber)

## Documentation

- Update inline code comments
- Update README.md for user-facing changes
- Update CHANGELOG.md with your changes
- Include JSDoc/PHPDoc for functions

## Questions?

- 📖 See [Wiki](https://github.com/Inamo87100/mc-ems-premium/wiki)
- 💬 Start a [Discussion](https://github.com/Inamo87100/mc-ems-premium/discussions)
- 🐛 Check [Issues](https://github.com/Inamo87100/mc-ems-premium/issues)

---

Thank you for contributing to make MC-EMS Premium better! 🙌