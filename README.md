# Darth - Building TYPO3 Release

Darth is a command line application to build and publish releases.

## The four steps to a release

### 1. Initializing the local setup

Sets up the working local repository by removing all previous set ups and re-cloning the remote repository.

    ./bin/darth init


### 2. Release (Commit and Tag)

Checks out a certain branch (based on the version), creates a signed commit via GPG key and pushes this to the
remote repository, and auto-approves via gerrit. Once the commit is in, a tag is created as well and pushed to
git (ensure that you have rights to add tags to the remote repository directly).

    ./bin/darth release [version]
        
        [version] can be either "8.7.3" or "8.7" for detecting the branch and the next version (of no specific version is set)
            
        --commitMessage -m  An additional information to the commit message of the release commit.
        --interactive   -i  Enabled by default, to verify that the right version and branch is used.
        --dry-run           No push to the remote git repository and gerrit is done, making all changes only in the local repository.
    

### 3. Package (Artefacts)

Creates artefacts (tar.gz and .zip) based on a specific version in the local git repository by calling `git-archive`,
`composer install` and removing left-over test and development files.

The artefacts are signed via GPG, and a README.md file is also created that contains the ChangeLog since the last
release, which is also signed via GPG.

All created files are put in the publish/[version]/artefacts folder.

    ./bin/darth package [version] [revision]
        
        [version] Used as naming scheme "8.7.3" for creating a package for "typo3_src-8.7.3.*" artefacts
        [revision] The revision to be used, if left empty, the version is assumed to be a tag.
            
        --type  Additional information if it is a snapshot release, security release. Will be added to README.md

    ./bin/darth package 9.1.0 v9.1.0

### 4. Publish (Upload)

Uploads the files to a Azure blob storage container (the container must exist).

All created files are put in the publish/[version]/artefacts folder.

    ./bin/darth publish [version]
        
        --type  Additional information if it is a snapshot release, then adds a "snapshot" to the folder.

    ./bin/darth publish 9.1.0

### 5. Announce (to get.typo3.org)

Uses the REST API to announce download packages, checksums, news link and
release note text to get.typo3.org. Checksums are recalculated from local
release packages in `publish/[version]/artefacts`.

    ./bin/darth announce [version] [news-link]
    
        --sprint-release Whether current release is a sprint release
        --force Whether to override existing information on get.typo3.org
        --interactive Whether to pause execution in order to adjust release notes manually
    
    ./bin/darth announce 9.2.0 https://new.typo3.org/article/typo3-v920-released/ --sprint-release
    ./bin/darth announce 8.7.12 https://new.typo3.org/article/typo3-8712-and-7626-released/

### 6. Security Advistories (to https://github.com/FriendsOfPHP/security-advisories)

In case the current release contains security fixes, these have to be prepared and announced
for https://github.com/FriendsOfPHP/security-advisories. The workflow is the following:

    ./bin/darth security [versions separated by comma]
    ./bin/darth security 9.5.2,8.7.21,7.6.32

Then merge generated files in `security/` directorty manually to `sensiolabs/security-advisories` package.

## Configuration

See .env.dist which sets most configuration options already, however, the remote connection string for uploading
artefacts must be set in a custom `.env` file.

Information when updating files before commiting a release, as well as files excluded for packaging are found
within `conf/release.yaml`.

Ensure that you have all tools (shasum, gpg, composer) installed locally, also that you have a proper gpg key
in your git configuration (`git config user.signingkey`, can also be set globally).

For MacOS users please install gnu-tar (`brew install gnu-tar`) and use that binary in your custom .env configuration
to ensure compatibility across all destination servers.

## Credits
Initially developed in PHP by Benni Mack, derived from Phing deployment scripts by Oliver Hader and Benni Mack, which
again derived from a Bash script from Michael Stucki.
