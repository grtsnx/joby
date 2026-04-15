# Contributing to Joby Sync

First off, thanks for taking the time to contribute! ❤️

All types of contributions are encouraged and valued. This document provides guidelines for how you can help, whether you are reporting a bug, suggesting a new feature, or submitting code changes.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How to Contribute](#how-to-contribute)
  - [Reporting Bugs](#reporting-bugs)
  - [Suggesting Enhancements](#suggesting-enhancements)
  - [Code Contributions](#code-contributions)
- [Coding Standards](#coding-standards)

---

## Code of Conduct

By participating in this project, you are expected to uphold our [Code of Conduct](CODE_OF_CONDUCT.md).

## How to Contribute

### Reporting Bugs
If you find a bug, please use our **Bug Report** template when opening an issue. Include:
- WordPress version
- PHP version
- Steps to reproduce

### Suggesting Enhancements
We welcome suggestions! Please use our **Feature Request** template to describe the problem you want to solve and your proposed solution.

### Code Contributions
1. **Fork** the repository.
2. **Create a branch** for your feature or fix: `git checkout -b feature/my-new-feature`.
3. **Make your changes**, ensuring you follow our [Coding Standards](#coding-standards).
4. **Test your changes** locally.
5. **Commit your changes**: `git commit -m "feat: add my new feature"`.
6. **Push** to your fork and submit a **Pull Request** against the `main` branch.

## Coding Standards

To maintain consistency and code quality, please adhere to the following:

- **WordPress Coding Standards**: We follow the [official WordPress Coding Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/).
- **Security**: All inputs must be sanitized, and all outputs must be escaped using WordPress security functions (e.g., `sanitize_text_field()`, `esc_html()`).
- **Documentation**: Please ensure your code follows standard PHP docblock practices.

---

*If you like the project but don't have time to contribute code, starring the repository or sharing it with others is also a great way to help!*
