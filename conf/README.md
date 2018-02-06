# TYPO3 {version} - Packaging Notes

This version was released and packaged on {releaseDate} (Git revision: {revision}).

This version is a {releaseType} release.

For more information check out https://get.typo3.org/release-notes/{version}

## Verification of Downloaded Files

### SHA256 checksums
<f:for each="{checksums}" as="sum">
    {sum}</f:for>

## ChangeLog

The following changes have been made since TYPO3 {previousVersion}:

<f:for each="{changeLog}" as="entry"> * {entry -> f:format.raw()}
</f:for>
