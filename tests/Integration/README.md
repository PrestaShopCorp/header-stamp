# Header Stamp Integration tests

These tests run the Header Stamp on sample modules to validate its ability to fix license headers.

- `tests/Resources/module-samples` folder contain module samples with issues in license headers
- `tests/Resources/expected` folder contain the same module samples, with valid (fixed) license headers
- `tests/Resources/workspace` is a git-ignored folder where files are copied then cleaned for the test needs

The test `UpdateLicencesCommandTest` does the following, for each sample module:
- copy it into a dedicated `tests/Resources/workspace` folder
- run the Header Stamp command on this folder
- compare 'tests/Resources/expected' folder with the result
- delete `tests/Resources/workspace` folder

You can simply run it like this:
```bash
$ composer integration-tests
```

If you want to add a module sample to the test suite, you need to:
- add the 'raw' version in `tests/Resources/module-samples`
- add the 'cleaned' version in `tests/Resources/expected`
- update the module list in `UpdateLicencesCommandTest`
