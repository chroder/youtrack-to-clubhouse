YouTrack to Clubhouse
=====================

This tool helps you migrate issues from YouTrack into Clubhouse.

Prepare
-------

* Copy `config/config.php.dist` to `config.php` and modify it with your values
* In YouTrack, increase the `Max Issues to Export` setting from Cog -> Global Settings -> Max Issues to Export
  * You want to set this really high, like `20000`
  * There's a bug in YouTrack API where it's impossible to page through issue links (such as the link between an Epic and a Subtask) using the export API. So the only way to get them all is to get them all in one go.
* In Clubhouse, you'll want to have your organisation all set up:
  * Invite all the members you want (so you can map authors etc)
  * Define your workflow now so you can sort stories from the get-go

Running
-------

This tool works in three stages that you run in order.

### 1. Download data

The first stage is to download all YouTrack data. You do this by
executing the `bin/yt-download.php` command.

```shell
$ php bin/yt-download.php
Issues ...
>>.......
Links ...
>>.....
```

This writes data from YouTrack as JSON files in the `data/` directory.

### 2. Init your mapper

Next step is to generate yourself a mapper:

```shell
$ php bin/init-mapper.php
Attempting to map usernames ...
 .. Done
Attempting to map issue types ...
 .. Done
Attempting to map states to workflows ...
 .. Done
Writing youtrack-to-clubhouse/config/mapper.php
Done
!!! You should review and edit this file as necessary..
```

This will generate a mapper at `config/mapper.php`. The mapper is a normal PHP class that contains logic to map YouTrack issues into Clubhouse stories and epics. The generated mapper gives you a good default. It will attempt to map users, issue types and workflows. But often you will need to do this yourself. The generated code includes all the values you need, you'll usually just need to copy+paste the correct Clubhouse value under the appropriate YouTrack value.

But you can also edit this file to make other changes as well. For example, you might want to add labels.

### 3. Import into Clubhouse

Finally, you're ready to import:

```shell
$ php bin/import-to-clubhouse.php
.......|.........||........
```

Dots represent created issues, pipes represent epics. If there are errors, you'll see a dump of an API response.

The import stats is saved to `data/import-status.json`. You should be able to cancel the command at any time and just re-start it. E.g. if you find your mapper needs a tweak, then you can stop the process and re-start it to resume where you left off.

