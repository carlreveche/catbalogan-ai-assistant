# Contributing to Catbalogan AI Assistant

Thank you for your interest in contributing! This document outlines guidelines for contributing to this project.

## Getting Started

1. **Fork the repository** on GitHub
2. **Clone your fork** locally
3. **Create a feature branch** from `main`:
   ```bash
   git checkout -b feature/your-feature-name
   ```

## Development Setup

```bash
# Clone the repository
git clone https://github.com/your-username/catbalogan-ai-assistant.git
cd catbalogan-ai-assistant

# Set up local environment using .env.example as reference
# Copy environment variable names and set them locally

# Set up XAMPP (for local development)
# - Copy folder to C:\xampp\htdocs
# - Start Apache and MySQL
# - Create database and import schema/seed data
```

## Making Changes

### Code Style

- **PHP**: Follow PSR-12 coding standard
- **JavaScript**: Use ES6 standards, avoid console logs in production
- **SQL**: Use prepared statements only (PDO)
- **CSS**: Use semantic class names

### Security Guidelines

- **Always use prepared statements** for database queries (PDO)
- **Escape all output** with `htmlspecialchars()` or similar
- **Validate all inputs** on both client and server side
- **Never commit secrets** or API keys
- **Use environment variables** for configuration
- **Implement CSRF tokens** on all forms

### Database Changes

1. Create migration notes in `database/migrations/` directory (if applicable)
2. Update `database/schema.sql` with any schema changes
3. Test thoroughly on a local copy
4. Document breaking changes

### Adding Features

1. Create a feature branch: `git checkout -b feature/feature-name`
2. Make your changes
3. Test locally on XAMPP
4. Ensure no secrets are committed
5. Write clear commit messages

### Testing Checklist

- [ ] Feature works locally
- [ ] No PHP errors or warnings
- [ ] Database queries use prepared statements
- [ ] All inputs are validated
- [ ] CSRF tokens are present on forms
- [ ] Output is properly escaped
- [ ] No secrets in code/commits
- [ ] Mobile-friendly (if UI change)

## Commit Messages

Use clear, descriptive commit messages:

```
feature: Add permit search functionality
fix: Correct typo in Tagalog keyword matching
docs: Update deployment guide
refactor: Simplify conversation history query
```

## Pull Request Process

1. **Push your feature branch** to your fork
2. **Create a Pull Request** against `main` branch
3. **Fill out the PR template** with:
   - Description of changes
   - Related issues (if any)
   - Testing steps
   - Screenshots (if UI change)
4. **Ensure CI passes** (if applicable)
5. **Address review feedback**
6. Maintainers will merge when ready

## Reporting Issues

### Bug Reports

Include:
- Clear description of the issue
- Steps to reproduce
- Expected behavior
- Actual behavior
- PHP/MySQL versions
- Screenshots if applicable

### Feature Requests

- Describe the desired feature
- Explain the use case
- Provide mockups or examples if possible

## Code of Conduct

- Be respectful and inclusive
- Provide constructive feedback
- Help others learn and improve
- Report inappropriate behavior

## Questions?

Feel free to:
- Open a GitHub Issue
- Check existing issues/discussions
- Review the README.md and DEPLOYMENT.md files

## Thank You!

Your contributions make this project better for everyone in the Catbalogan community.
