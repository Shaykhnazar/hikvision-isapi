# Contributing

Two kinds of contribution are worth more than the rest, so they come first.

## Report a device

Nobody has confirmed this package against a real terminal. If you have one, a
compatibility report is the single most useful thing you can send — it closes
questions the package genuinely cannot answer from the specification.

```bash
HIKVISION_PASSWORD='...' php vendor/bin/hikvision-probe --host=192.168.1.100
```

The probe is read-only: it never creates, updates or deletes anything on the
device, and even the refusals it records are provoked with reads. It puts no
employee name, card number or employee number in the report — the person list
is read for its *shape* only, field names kept and field values discarded — and
the address you probed is not in it either. **Read the file before you attach
it**, as the tool tells you to.

Open a pull request adding a row to the compatibility table in the README, with
the report attached to it. The report answers every column.

## Report a failure that is silent

Bugs where nothing throws are the ones this package cares most about: a list
that never reaches its end, a count that is confidently zero, a search that
serves page one forever. If something *looks* fine and is wrong, say so even if
you cannot explain it — the reproduction matters more than the diagnosis.

Please include the device model and firmware, and the probe report if you can
run it.

## Changing the code

```bash
composer install
vendor/bin/phpunit          # 139 tests
vendor/bin/pint             # code style
vendor/bin/phpstan analyse  # level 6, no baseline
```

All three run in CI across PHP 8.2/8.3/8.4 and Laravel 12/13. A pull request
that does not pass them locally will not pass there.

### What a good change looks like here

**A test that fails for the right reason.** Write the test, watch it fail,
*then* fix it. More than once in this package's history a test passed while
measuring the wrong thing — a mock that recorded its own arguments, a filter
that let the test class through, a frozen clock that hid a bug living at a
second boundary. A test that has never failed has not been shown to work.

**Nothing invented.** Where device behaviour is unknown, this package leaves
the question open and says why, rather than shipping a plausible guess. Several
`subStatusCode` classifications are deliberately missing for exactly this
reason. If your change depends on how a device behaves, either confirm it with
a probe report or mark it as unconfirmed.

**A comment that says why, not what.** The code says what it does. Comments
here are for the reasoning a later reader cannot recover — which failure a
guard prevents, what was tried and rejected, what is still not known.

## Security

Do not open a public issue for a security problem. See
[SECURITY.md](SECURITY.md).

## Questions

GitHub Issues, or shaykhnazar@gmail.com.
