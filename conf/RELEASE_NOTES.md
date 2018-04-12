# News Link

[/newsLink/] <>
{newsLink}
[/newsLink/] <>

# News

[/news/] <>
<f:if condition="{releaseType}=='security'"><f:then>
This release is a combined bug fix and security release.

Find more details in the security bulletins:

+ https://typo3.org/
+ https://typo3.org/
+ https://typo3.org/
</f:then><f:else>
This version is a bugfix and maintenance release.
</f:else></f:if>
[/news/] <>

# Upgrade Instructions

[/upgradingInstructions/] <>
<f:if condition="{sprintRelease}"><f:then>
1. Before you update any instance to {version}, have a backup in place.
1. Now download the new core and present it to your instance (by symlink or copied files)
1. Use the install tool to run the upgrade wizards
1. Use the install tool to clear each and every cache you can find, even opcode.
1. When you encounter compatibility problems with your extensions, look for the Git versions around in order to find one already upgraded.
</f:then><f:else>
The usual [upgrading procedure](https://docs.typo3.org/typo3cms/InstallationGuide/)
applies. No database updates are necessary.  It might be required to clear all caches;
the "important actions" section in the TYPO3 Install Tool offers the accordant possibility
to do so.
</f:else></f:if>
[/upgradingInstructions/] <>

# Changes

[/changes/] <>
<f:if condition="{sprintRelease}">
* [{minorVersion} ChangeLog](https://docs.typo3.org/typo3cms/extensions/core/Changelog/{minorVersion}/Index.html)
    + [Features](https://docs.typo3.org/typo3cms/extensions/core/Changelog/{minorVersion}/Index.html#features)
    + [Important Changes](https://docs.typo3.org/typo3cms/extensions/core/Changelog/{minorVersion}/Index.html#important)
    + [Breaking Changes](https://docs.typo3.org/typo3cms/extensions/core/Changelog/{minorVersion}/Index.html#breaking-changes)
    + [Deprecation Changes](https://docs.typo3.org/typo3cms/extensions/core/Changelog/{minorVersion}/Index.html#deprecation)

</f:if>
<f:for each="{changes}" as="entry"> * {entry -> f:format.raw()}
</f:for>
[/changes/] <>
