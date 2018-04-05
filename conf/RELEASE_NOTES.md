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

[/upgradeInstructions/] <>
<f:if condition="{sprintRelease}"><f:then>
0. Before you update any instance to {version}, have a backup in place.
0. Now download the new core and present it to your instance (by symlink or copied files)
0. Use the install tool to run the upgrade wizards
0. Use the install tool to clear each and every cache you can find, even opcode.
0. When you encounter compatibility problems with your extensions, look for the Git versions around in order to find one already upgraded.
</f:then><f:else>
The usual [upgrading procedure](https://docs.typo3.org/typo3cms/InstallationGuide/)
applies. No database updates are necessary.  It might be required to clear all caches;
the "important actions" section in the TYPO3 Install Tool offers the accordant possibility
to do so.
</f:else></f:if>
[/upgradeInstructions/] <>

# Changes

[/changes/] <>
<f:for each="{changes}" as="entry"> * {entry -> f:format.raw()}
</f:for>
[/changes/] <>
