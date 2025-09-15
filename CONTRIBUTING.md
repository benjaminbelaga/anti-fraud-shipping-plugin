# Contributing to WooCommerce Fraud Shield

🙏 **Thank you for your interest in contributing to WooCommerce Fraud Shield!**

This document provides guidelines for contributing to this anti-fraud security plugin for WooCommerce.

---

## 🚀 **Getting Started**

### Prerequisites
- **PHP**: 7.4 or higher
- **WordPress**: 5.0 or higher
- **WooCommerce**: 3.0 or higher
- **Git**: For version control
- **Composer**: For dependency management (if applicable)

### Development Environment Setup
```bash
# Clone the repository
git clone https://github.com/yoyaku-tech/woocommerce-fraud-shield.git
cd woocommerce-fraud-shield

# Set up WordPress development environment
# (Use Local, XAMPP, or your preferred WordPress setup)

# Create a symbolic link to your WordPress plugins directory
ln -s $(pwd) /path/to/wordpress/wp-content/plugins/woocommerce-fraud-shield
```

---

## 🎯 **How to Contribute**

### 🐛 **Bug Reports**
If you find a bug, please create an issue with:

1. **Clear Title**: Descriptive summary of the issue
2. **Steps to Reproduce**: Detailed reproduction steps
3. **Expected Behavior**: What should happen
4. **Actual Behavior**: What actually happens
5. **Environment Details**:
   - WordPress version
   - WooCommerce version
   - PHP version
   - Plugin version
   - Browser (if frontend issue)

### 💡 **Feature Requests**
For new features:

1. **Use Case**: Explain why this feature is needed
2. **Proposed Solution**: Describe your ideal implementation
3. **Alternatives**: Any alternative approaches considered
4. **Security Impact**: How this affects fraud prevention

### 🔧 **Code Contributions**

#### Pull Request Process
1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/amazing-feature`)
3. **Follow** coding standards (see below)
4. **Write** or update tests
5. **Update** documentation
6. **Commit** your changes (`git commit -m 'Add amazing feature'`)
7. **Push** to your branch (`git push origin feature/amazing-feature`)
8. **Create** a Pull Request

#### Branch Naming Convention
- `feature/feature-name` - New features
- `bugfix/issue-description` - Bug fixes
- `hotfix/critical-issue` - Critical security fixes
- `docs/improvement` - Documentation updates

---

## 📝 **Coding Standards**

### 🎨 **WordPress Coding Standards**
We follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/):

```bash
# Install PHP CodeSniffer with WordPress standards
composer global require "squizlabs/php_codesniffer=*"
composer global require wp-coding-standards/wpcs

# Check code compliance
phpcs --standard=WordPress /path/to/plugin/file.php

# Auto-fix issues where possible
phpcbf --standard=WordPress /path/to/plugin/file.php
```

### 🔐 **Security Guidelines**
**CRITICAL**: This is a security plugin - follow these rules:

1. **Input Validation**: Always validate and sanitize user inputs
2. **Output Escaping**: Escape all outputs using appropriate WordPress functions
3. **SQL Injection Prevention**: Use `$wpdb->prepare()` for all database queries
4. **Nonce Verification**: Verify nonces for all form submissions
5. **Capability Checks**: Verify user permissions before sensitive operations

```php
// ✅ Good - Properly escaped output
echo '<div>' . esc_html($user_input) . '</div>';

// ✅ Good - Prepared SQL statement
$results = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM table WHERE id = %d", $id)
);

// ❌ Bad - Direct output without escaping
echo '<div>' . $user_input . '</div>';

// ❌ Bad - SQL injection risk
$results = $wpdb->get_results("SELECT * FROM table WHERE id = $id");
```

### 📚 **Documentation Standards**
- **PHPDoc Comments**: Document all functions, classes, and methods
- **Inline Comments**: Explain complex logic
- **README Updates**: Update documentation for new features

```php
/**
 * Analyze cart contents for suspicious products
 *
 * @since 1.0.0
 * @return array {
 *     Cart analysis results
 *
 *     @type int    $total_items         Total items in cart
 *     @type int    $suspicious_items    Number of suspicious items
 *     @type array  $suspicious_products Array of suspicious product IDs
 *     @type bool   $only_suspicious     Whether cart contains only suspicious products
 *     @type float  $cart_total          Total cart value
 * }
 */
private function analyze_cart() {
    // Implementation...
}
```

---

## 🧪 **Testing**

### 🎯 **Testing Requirements**
All contributions must include appropriate tests:

1. **Unit Tests**: Test individual functions
2. **Integration Tests**: Test WooCommerce integration
3. **Security Tests**: Verify fraud prevention logic
4. **Manual Testing**: Test in real WordPress environment

### 🔍 **Test Scenarios**
Always test these critical scenarios:

```php
// Test Case 1: Security fee activation
$cart = [604098]; // Suspicious product only
$result = $this->analyze_cart($cart);
$this->assertTrue($result['only_suspicious']);

// Test Case 2: Mixed cart behavior
$cart = [604098, 12345]; // Suspicious + normal
$result = $this->analyze_cart($cart);
$this->assertFalse($result['only_suspicious']);

// Test Case 3: Normal shopping
$cart = [12345, 67890]; // Normal products only
$result = $this->analyze_cart($cart);
$this->assertEquals(0, $result['suspicious_items']);
```

### 🚨 **Security Testing**
Test fraud prevention scenarios:

1. **Solo Suspicious Product**: Verify 9999€ fee applies
2. **Mixed Cart**: Verify normal shipping shows
3. **Cache Bypass**: Verify caching doesn't break detection
4. **IP Logging**: Verify fraud attempts are logged
5. **Admin Access**: Verify debug console shows for admins only

---

## 🔄 **Release Process**

### 📋 **Before Release**
1. **Code Review**: Security and performance audit
2. **Testing**: All test scenarios pass
3. **Documentation**: README and changelog updated
4. **Version Bump**: Update plugin header and constants
5. **Staging Test**: Deploy to staging environment

### 📦 **Version Numbering**
We use [Semantic Versioning](https://semver.org/):

- **MAJOR**: Breaking changes (2.0.0)
- **MINOR**: New features, backward compatible (1.1.0)
- **PATCH**: Bug fixes (1.0.1)

### 🏷️ **Git Tags**
```bash
# Create and push release tag
git tag -a v1.0.0 -m "Release version 1.0.0"
git push origin v1.0.0
```

---

## 🚫 **What NOT to Contribute**

### ❌ **Prohibited Changes**
- **Breaking Security**: Never weaken fraud detection
- **Performance Degradation**: Avoid heavy operations in critical paths
- **WordPress Violations**: Don't bypass WordPress standards
- **Data Exposure**: Never log sensitive customer data
- **Backdoors**: No hidden functionality or access points

### 🚨 **Security Vulnerabilities**
If you discover a security vulnerability:

1. **DO NOT** create a public issue
2. **Email** tech@yoyaku.io with details
3. **Wait** for our response before disclosure
4. **Follow** responsible disclosure practices

---

## 📞 **Getting Help**

### 💬 **Communication Channels**
- **GitHub Issues**: For bugs and feature requests
- **Email**: tech@yoyaku.io for security issues
- **Documentation**: Check README and inline docs first

### 🤝 **Code Review Process**
1. **Automated Checks**: GitHub Actions run tests and standards checks
2. **Security Review**: Manual security audit for all changes
3. **Functionality Test**: Verify on staging environment
4. **Final Approval**: YOYAKU Tech Team approval required

---

## 🏆 **Recognition**

### 🌟 **Contributors**
All contributors will be recognized in:
- **README.md**: Contributors section
- **CHANGELOG.md**: Release notes
- **Plugin Credits**: WordPress admin area

### 🎖️ **Contributor Levels**
- **Code Contributors**: Bug fixes, features, tests
- **Documentation Contributors**: README, guides, tutorials
- **Security Contributors**: Vulnerability reports, security improvements
- **Community Contributors**: Support, issue triage, testing

---

## 📄 **License**

By contributing to WooCommerce Fraud Shield, you agree that your contributions will be licensed under the GPL v2 or later license.

---

**Thank you for helping make WooCommerce more secure! 🛡️**

*YOYAKU Tech Team*