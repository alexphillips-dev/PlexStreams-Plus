# PlexStreams Plus

Unraid plugin to display active Plex streams in the dashboard and tools pages.

## Install In Unraid

In Unraid, go to `Plugins` -> `Install Plugin` and paste:

`https://raw.githubusercontent.com/alexphillips-dev/PlexStreams-Plus/main/plexstreamsplus.plg`

For legacy installs that still track the old plugin filename, this compatibility URL is also available:

`https://raw.githubusercontent.com/alexphillips-dev/PlexStreams-Plus/main/plexstreams.plg`

## Release Pipeline

Use GitHub Actions to publish new plugin updates:

1. Open `Actions` in GitHub and run the `Release Plugin` workflow.
2. Leave inputs empty for auto mode:
   - Date uses `America/New_York` (`YYYY.MM.DD`)
   - Revision auto-increments as `.1`, `.2`, `.3`, etc.
3. The workflow builds both archives, updates md5 values, updates plugin versions, commits, and pushes to `main`.
