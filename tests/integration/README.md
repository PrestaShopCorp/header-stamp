# Header Stamp Integration tests

These tests run the Header Stamp on sample modules to validate its ability to fix license headers.

- `module-samples` contain module samples with issues in license headers
- `expected` contain the same module samples, with valid (fixed) license headers

The test `runner/run.php` does the following, for each sample module:
- copy it into a dedicated `workspace` folder
- run the Header Stamp application on this folder
- compare 'expected' folder with the result
- delete `workspace` folder

You can simply run it like this:
```bash
$ php runner/run.php
```
