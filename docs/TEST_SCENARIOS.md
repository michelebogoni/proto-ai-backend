# CREATOR - End-to-End Test Scenarios

## Comprehensive Test Suite Documentation

**Version:** 1.0
**Last Updated:** 2025-12-04
**Status:** Milestone 6 Complete

---

## Table of Contents

1. [Overview](#overview)
2. [Test Suite Structure](#test-suite-structure)
3. [Test Scenarios](#test-scenarios)
4. [Running Tests](#running-tests)
5. [Test Coverage](#test-coverage)
6. [Troubleshooting](#troubleshooting)

---

## Overview

This document describes the comprehensive end-to-end test suite for Creator. The tests verify all critical paths work correctly, including:

- Phase detection and transitions
- Code execution (WP Code and fallback)
- Verification of executed actions
- Rollback functionality
- Error handling and recovery
- File attachment processing
- Lazy-load context system
- Conversation history management

---

## Test Suite Structure

```
tests/
├── bootstrap.php              # PHPUnit bootstrap
├── stubs/
│   └── wordpress-stubs.php    # WordPress function mocks
├── Unit/                      # Unit tests
│   ├── ChatInterfaceTest.php
│   ├── RollbackTest.php
│   ├── SnapshotManagerTest.php
│   └── ...
└── Integration/               # Integration/E2E tests
    ├── PhaseDetectorIntegrationTest.php
    ├── CodeExecutorIntegrationTest.php
    ├── RollbackIntegrationTest.php
    ├── ErrorScenarioTest.php
    ├── FileAttachmentTest.php
    ├── LazyLoadContextTest.php
    └── ConversationHistoryTest.php
```

---

## Test Scenarios

### Scenario 1: Discovery → Proposal → Execution Flow

**Test File:** `PhaseDetectorIntegrationTest.php`

**Description:** Complete conversation flow from initial request to code execution.

**Steps:**
1. User makes initial request ("Crea un CPT per progetti")
2. AI enters DISCOVERY phase, asks clarifying questions
3. User answers questions
4. AI enters PROPOSAL phase, proposes plan with credits estimate
5. User confirms
6. AI enters EXECUTION phase, generates and executes code

**Expected Results:**
- ✅ Each phase correctly detected
- ✅ User input correctly classified
- ✅ Phase transitions are valid
- ✅ Execution phase marked as final

**Status:** PASS

---

### Scenario 2: Code Execution with WP Code

**Test File:** `CodeExecutorIntegrationTest.php`

**Description:** Code execution using WP Code snippet as primary method.

**Steps:**
1. AI generates PHP code for CPT registration
2. Security validation passes (no forbidden functions)
3. WP Code snippet created
4. Snippet activated
5. Verification confirms CPT exists

**Expected Results:**
- ✅ Code passes security check
- ✅ Snippet created successfully
- ✅ Result includes snippet_id
- ✅ Rollback method documented

**Status:** PASS

---

### Scenario 3: Code Execution Fallback (Custom Files)

**Test File:** `CodeExecutorIntegrationTest.php`

**Description:** Fallback to custom files when WP Code not available.

**Steps:**
1. WP Code not available
2. System detects code type (PHP/CSS/JS)
3. Code written to appropriate custom file
4. Modification registered in manifest
5. Snapshot created for rollback

**Expected Results:**
- ✅ Fallback path executed
- ✅ Code written to codice-custom.php
- ✅ Manifest updated
- ✅ Snapshot available

**Status:** PASS

---

### Scenario 4: Verification Tests

**Test File:** `CodeExecutorIntegrationTest.php`

**Verification Checks:**

| Check | Description | Pass Criteria |
|-------|-------------|---------------|
| CPT Creation | Custom post type registered | `post_type_exists('cpt_name')` |
| ACF Field | ACF field group registered | Field group in ACF registry |
| Post Creation | New post created | Valid post_id returned |
| Meta Update | Post meta updated | `get_post_meta()` returns value |

**Status:** PASS

---

### Scenario 5: Rollback - Fresh Snapshot

**Test File:** `RollbackIntegrationTest.php`

**Description:** Undo action with fresh snapshot (< 24 hours).

**Steps:**
1. User clicks Undo button
2. System finds valid snapshot
3. Operations restored to before state
4. Success message displayed

**Expected Results:**
- ✅ Snapshot found and valid
- ✅ All operations rolled back
- ✅ Success returned with details
- ✅ Previous state restored

**Status:** PASS

---

### Scenario 6: Rollback - Expired Snapshot

**Test File:** `RollbackIntegrationTest.php`

**Description:** Undo attempt with expired snapshot (> 24 hours).

**Steps:**
1. User clicks Undo button
2. System detects snapshot is expired
3. Appropriate error message returned
4. Suggestion to use backup system

**Expected Results:**
- ✅ Expiration detected
- ✅ Clear error message
- ✅ Recovery suggestion provided
- ✅ No data corruption

**Status:** PASS

---

### Scenario 7: Rollback - Partial Success

**Test File:** `RollbackIntegrationTest.php`

**Description:** Rollback where some operations fail.

**Steps:**
1. Multi-operation rollback attempted
2. Some operations succeed, some fail
3. Results aggregated
4. Partial success reported

**Expected Results:**
- ✅ Individual operation results tracked
- ✅ Partial success handled gracefully
- ✅ Failed operations documented
- ✅ User informed of status

**Status:** PASS

---

### Scenario 8: Error - Syntax Error in Code

**Test File:** `ErrorScenarioTest.php`

**Description:** Handling of PHP syntax errors.

**Test Cases:**
- Unclosed brackets
- Unclosed strings
- Missing semicolons
- Invalid PHP syntax

**Expected Results:**
- ✅ Error detected before execution
- ✅ User-friendly error message
- ✅ Code not executed
- ✅ No side effects

**Status:** PASS

---

### Scenario 9: Error - Forbidden Function

**Test File:** `ErrorScenarioTest.php`

**Description:** Blocking of dangerous functions.

**Forbidden Functions Tested:**
- `exec`, `shell_exec`, `system`, `passthru`
- `eval`, `assert`, `create_function`
- `unlink`, `rmdir`, `chmod`
- `popen`, `proc_open`
- Backtick execution

**Expected Results:**
- ✅ All forbidden functions blocked
- ✅ Violations listed in response
- ✅ Helpful error message
- ✅ No execution attempted

**Status:** PASS

---

### Scenario 10: Error - Max Retries Exceeded

**Test File:** `ErrorScenarioTest.php`

**Description:** Handling when AI retry limit reached.

**Steps:**
1. Code execution fails
2. AI retries with modified code
3. Failure persists through 5 attempts
4. Max retries message displayed
5. Manual intervention suggested

**Expected Results:**
- ✅ Retry count tracked
- ✅ Limit enforced (5 max)
- ✅ Clear final message
- ✅ Recovery options provided

**Status:** PASS

---

### Scenario 11: File Attachment - Image

**Test File:** `FileAttachmentTest.php`

**Description:** AI receives and analyzes attached image.

**Test Cases:**
- PNG screenshot of error
- JPEG design mockup
- WebP logo

**Expected Results:**
- ✅ File validated (size, type)
- ✅ Base64 encoded correctly
- ✅ Provider format correct (Gemini/Claude)
- ✅ AI acknowledges file in response

**Status:** PASS

---

### Scenario 12: File Attachment - PDF

**Test File:** `FileAttachmentTest.php`

**Description:** AI receives and reads PDF document.

**Test Cases:**
- Requirements PDF
- Design specifications
- Project brief

**Expected Results:**
- ✅ PDF validated
- ✅ Content extracted (if possible)
- ✅ AI references PDF content
- ✅ Uses requirements in plan

**Status:** PASS

---

### Scenario 13: Lazy-Load Context

**Test File:** `LazyLoadContextTest.php`

**Description:** On-demand loading of plugin details.

**Steps:**
1. AI requests plugin details (context_request)
2. System loads from repository
3. Details cached
4. Injected into next prompt
5. AI uses in code generation

**Expected Results:**
- ✅ Context request recognized
- ✅ Details loaded successfully
- ✅ Cache populated
- ✅ AI uses correct functions

**Status:** PASS

---

### Scenario 14: Long Conversation (20+ messages)

**Test File:** `ConversationHistoryTest.php`

**Description:** History management for long conversations.

**Steps:**
1. Conversation exceeds 10 messages
2. Older messages summarized
3. Recent 10 messages kept complete
4. Context maintained

**Expected Results:**
- ✅ History pruned correctly
- ✅ Summary is concise (2-3 lines)
- ✅ Key context preserved
- ✅ Token budget maintained

**Status:** PASS

---

## Running Tests

### Run All Tests

```bash
cd packages/creator-core-plugin/creator-core
./vendor/bin/phpunit
```

### Run Unit Tests Only

```bash
./vendor/bin/phpunit --testsuite Unit
```

### Run Integration Tests Only

```bash
./vendor/bin/phpunit --testsuite Integration
```

### Run Specific Test File

```bash
./vendor/bin/phpunit tests/Integration/PhaseDetectorIntegrationTest.php
```

### Run with Coverage

```bash
./vendor/bin/phpunit --coverage-html coverage/html
```

---

## Test Coverage

### Coverage Goals

| Component | Target | Current |
|-----------|--------|---------|
| ChatInterface | 80% | ✅ |
| CodeExecutor | 85% | ✅ |
| PhaseDetector | 90% | ✅ |
| Rollback | 80% | ✅ |
| SnapshotManager | 75% | ✅ |
| SystemPrompts | 70% | ✅ |

### Critical Paths Covered

- ✅ Phase detection (Discovery/Proposal/Execution)
- ✅ Code security validation
- ✅ WP Code snippet creation
- ✅ Custom file fallback
- ✅ Snapshot creation and restoration
- ✅ Rollback execution
- ✅ Error handling
- ✅ File attachment validation
- ✅ Context lazy-loading
- ✅ History pruning

---

## Troubleshooting

### Common Test Failures

#### 1. "Class not found" errors

**Cause:** Autoloader not configured correctly

**Solution:**
```bash
composer dump-autoload
```

#### 2. Database-related failures

**Cause:** Tests requiring database not mocked properly

**Solution:** Check `wordpress-stubs.php` includes necessary mocks

#### 3. File permission errors

**Cause:** Custom file tests need write permission

**Solution:**
```bash
chmod -R 755 tests/
```

#### 4. Memory limit exceeded

**Cause:** Large test suite or coverage generation

**Solution:**
```bash
./vendor/bin/phpunit --memory-limit=512M
```

---

## Changelog

### Version 1.0 (2025-12-04)
- Initial test suite created
- Integration tests for all Milestone 6 scenarios
- Documentation complete
- All tests passing

---

**Document Version:** 1.0
**Maintained by:** Creator Development Team
