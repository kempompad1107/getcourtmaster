---
name: logging-ignore-exceptions-todo
description: "Uncommitted change on live — config/logging.php has ignore_exceptions set to true, should be reverted to false locally"
metadata: 
  node_type: memory
  type: project
  originSessionId: 2113b2c5-d4c5-4f1b-9985-731e5a39ec6a
---

**To-do (local fix only — do NOT change on live):**

In `config/logging.php`, the `stack` channel has an uncommitted change:

```php
// Current on live (wrong — should be reverted locally):
'ignore_exceptions' => true,

// Should be:
'ignore_exceptions' => false,
```

**Why:** This change is sitting uncommitted on live. It silences logging exceptions, which can hide errors. It was not intentionally committed.

**How to apply:** On your local machine, run:

```bash
git checkout config/logging.php
```

This reverts the file to the committed state (`ignore_exceptions => false`). Then test locally and commit only if there's a real reason to change it.
