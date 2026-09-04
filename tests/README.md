# Testing Guide

This document describes the test suite for the Laravel Upload Manager package.

## Test Structure

Tests are organized into three levels:

### Unit Tests (`tests/Unit/`)

Low-level component tests that verify individual classes work correctly in isolation.

**Coverage:**
- `StreamHasherTest.php` - File hashing with various algorithms, chunk sizes, large files
- `FilenameGeneratorTest.php` - Filename generation strategies (uuid, original, hash-based)
- `PathGeneratorTest.php` - Path generation with date placeholders

**Key Tests:**
- Hash algorithm correctness (MD5, SHA1, SHA256)
- Content-based deduplication
- Date placeholder resolution (`{year}`, `{month}`, `{day}`)
- Chunk-based processing

### Feature Tests (`tests/Feature/`)

Mid-level tests that verify the main UploadManager class works correctly with various configurations.

**Coverage:**
- `UploadManagerTest.php` - Main upload functionality

**Key Tests:**
- Basic file uploads
- Different filename strategies (uuid, original, sha256)
- Profile-based uploads (documents, images, encrypted)
- Hash computation
- Large file handling
- File metadata capture
- Error handling

### Integration Tests (`tests/Integration/`)

End-to-end tests that verify the complete upload flow works correctly across multiple scenarios.

**Coverage:**
- `UploadIntegrationTest.php` - Full upload workflows

**Key Tests:**
- Complete upload flow
- Content-based deduplication
- Multiple sequential uploads
- Profile configuration enforcement
- File retrieval and deletion
- Edge cases (special characters, long names, multiple dots)

## Running Tests

### Run all tests
```bash
./vendor/bin/pest
```

### Run specific test suite
```bash
# Unit tests only
./vendor/bin/pest tests/Unit

# Feature tests only
./vendor/bin/pest tests/Feature

# Integration tests only
./vendor/bin/pest tests/Integration
```

### Run specific test file
```bash
./vendor/bin/pest tests/Unit/StreamHasherTest.php
```

### Run with coverage
```bash
./vendor/bin/pest --coverage
```

### Run in parallel
```bash
./vendor/bin/pest --parallel
```

### Run with detailed output
```bash
./vendor/bin/pest -v
```

## Test Database/Storage

Tests use a temporary test disk (`storage/testing/`) for file operations. This directory is:
- Created before each test suite
- Cleaned up after each test suite
- Isolated from application data

## Test Helpers

### `mockUploadedFile()`
Convenient helper for creating fake uploaded files:

```php
// Create 1KB file with specific name
$file = mockUploadedFile('document.pdf', 1000);

// Create file with specific content
$file = mockUploadedFile('test.txt', 0, 'file content here');
```

## Test Coverage

Target coverage areas:

| Component | Coverage |
|-----------|----------|
| StreamHasher | 100% |
| FilenameGenerator | 100% |
| PathGenerator | 100% |
| UploadManager | 95%+ |
| Encryption | Basic (full test requires real keys) |

## Important Test Details

### Streaming Verification

Tests verify streaming behavior without testing actual memory usage:
- Files are uploaded successfully
- Storage operations complete correctly
- Content remains intact

Real memory profiling should be done with actual large files.

### Hash Testing

Tests verify:
- Hash algorithms produce correct values
- Identical content produces identical hashes
- Different content produces different hashes
- Content-based deduplication works

### Date Placeholders

Tests mock the current date using `Carbon::setTestNow()` to ensure date-based path generation is deterministic.

### Encryption

Basic encryption tests verify:
- Encryption flag is respected
- Configuration is passed correctly
- Upload completes successfully

Full encryption testing requires:
- Integration with actual Laravel Crypt
- Decryption verification
- Large file encryption testing

## CI/CD Integration

For CI/CD pipelines, run:

```bash
./vendor/bin/pest --coverage --coverage-html=coverage
```

This generates:
- Test results
- Code coverage report (HTML in `coverage/` directory)
- Exit code 0 on success

## Debugging Tests

### Run single test with output
```bash
./vendor/bin/pest tests/Unit/StreamHasherTest.php::test --verbose
```

### Stop on first failure
```bash
./vendor/bin/pest --stop-on-failure
```

### Run with ray debugging (requires ray.so)
```bash
./vendor/bin/pest --debug
```

## Common Test Issues

### Test files not found
Ensure test files are in the correct directory structure:
```
tests/
├── Unit/
├── Feature/
└── Integration/
```

### Storage directory permission errors
Run tests with proper permissions:
```bash
chmod -R 755 storage/
```

### Configuration not loading
Tests extend `TestCase::class` which loads `config/upload-manager.php`. Ensure config is in the correct location.

## Adding New Tests

When adding new features:

1. **Add unit tests** for individual components
2. **Add feature tests** for UploadManager configuration
3. **Add integration tests** for full workflows

Use the existing test structure as a template.

Example test structure:
```php
describe('Feature Name', function () {
    it('does something', function () {
        $result = performAction();
        expect($result)->toBe(expected);
    });
});
```

## Performance Testing

For performance tests (not included in standard suite):

```bash
# Profile test execution time
php -d memory_limit=-1 ./vendor/bin/pest tests/ --profile
```

## Test Standards

- All public methods should have test coverage
- Tests should be independent and not rely on execution order
- Use descriptive test names (`it('does something specific')`)
- Clean up resources in `afterEach()` hooks
- Mock external dependencies appropriately
