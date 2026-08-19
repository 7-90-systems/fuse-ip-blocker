# Fuse IP Blocker

Blocks IP addresses from reaching a WordPress site. Blocked visitors are served a
configurable message and the request stops there. Every block is logged, so you can
see which addresses are being turned away and what they were asking for.

Addresses can also be whitelisted, which keeps them in even when they match a block.

Works with both IPv4 and IPv6, and can block or whitelist a single address or a range.

- **Author:** 7-90 Systems — <https://7-90.com.au>
- **Plugin URI:** <https://fusecms.org>
- **Version:** 1.0
- **Requires:** WordPress 6.4+, PHP 8.1+
- **Text domain:** `fuseip`

## Requires Fuse CMS

This is a companion plugin to the **Fuse CMS Framework**, not a standalone one. It
relies on the framework for its class autoloading (through the
`FUSE_PLUGIN_IPBLOCKER_BASE_URI` constant), for the `Fuse\Traits\Singleton` trait,
for the `fuse_init` action it starts up on, and for the **Fuse CMS** admin menu it
adds its page beneath. Fuse CMS also performs its update checks.

Without Fuse CMS active, the admin screen will not appear.

## Installation

Activate the plugin. On activation it creates two database tables:

| Table | Holds |
| --- | --- |
| `{prefix}fuseip_blocks` | One row per blocked address or range, with its description, when it was added, when it last blocked something, and how many times |
| `{prefix}fuseip_logs` | One row per blocked request, with the time, the requested URL and the actual remote address |
| `{prefix}fuseip_whitelist` | One row per whitelisted address or range, with its description, when it was added, when it last let somebody through, and how many times |

### Schema changes

The tables carry a version, in the `fuse_ipblocker_schema` option, and the plugin
checks it on every admin load. A site that updates the plugin without deactivating it
first still gets any new columns, because activation is not the only thing that builds
the tables. Once the version is current the check is a single option read.

| Version | Change |
| --- | --- |
| 1 | The original two tables |
| 2 | `description` on a block |
| 3 | The whitelist table |

## Usage

Go to **Fuse CMS → IP Blocker**. The screen has two tabs:

| Tab | What it holds |
| --- | --- |
| **Blocked addresses** | The block list. Opens by default |
| **Whitelist** | Addresses that are never blocked |

Each tab lists its entries and lets you add a new one or delete an existing one. Both
actions happen over AJAX without a page reload. Selecting an address from the block
list shows the logged requests for it.

Either kind of entry can carry an optional **description** — free text up to 255
characters, entered when the entry is added and shown in its own column, so the reason
for it is still there months later. It is stripped of tags on the way in and escaped on
the way out.

The page and all four AJAX endpoints require the `manage_options` capability, and the
endpoints are nonce-checked.

## The whitelist

A whitelisted address is never blocked, even when it matches a block. That is what
makes a wide block usable: block `203.0.113.` and whitelist `203.0.113.40`, and the
range is closed apart from the one address you need in.

Entries are written and matched exactly as blocks are — a full address or the leading
part of one — so everything under **Blocking a range** applies to them too.

The whitelist is only consulted **after** a block has already matched, so an ordinary
visitor never pays for it. That is also why `last_allowed` and `allow_count` move only
when an entry has actually kept somebody in; an entry that has never been needed shows
as *Never used*, which is the quiet, correct state rather than a warning. A whitelisted
request writes nothing to the logs, because it was never blocked.

## Blocking a range

Matching is done from the left of the address, so removing the end of an address
widens it into a range.

**IPv4**

- `127.0.0.1` blocks that single address
- `127.0.0.` blocks everything from `127.0.0.0` to `127.0.0.255`

**IPv6**

- `2001:0db8:85a3:0000:0000:8a2e:0370:7334` blocks that single address
- `2001:0db8:85a3:0000:0000:8a2e:0370:` blocks that range

Entries are validated before they are stored. A partial address must stop at a
separator -- a dot for IPv4, a colon for IPv6 -- because that is what keeps the match
on a boundary. Without it, `192.0.1` would also catch `192.0.10.x` and `192.0.199.x`.

Also rejected: octets above 255, octets with a leading zero (`010.` and `10.` would be
stored as different blocks meaning the same thing), and IPv6 ranges written with `::`
shorthand. A block is compared as plain text, so `2001:db8::` would never line up
against `2001:db8:85a3::1` -- write `2001:db8:` instead.

A complete IPv6 address is normalised to the short form PHP reports in `REMOTE_ADDR`,
so pasting the fully expanded `2001:0db8:85a3:0000:0000:8a2e:0370:7334` works and is
stored as `2001:db8:85a3::8a2e:370:7334`.

## Block status levels

The block list colours two of its columns, on two separate measures. Each level is
shown by colour alone; its name is not printed.

### Block age

The **Last Blocked** column is coloured by how long it has been since that block last
stopped a request. A block that has just fired is a live problem; one that has not
fired in months probably is not, and is a candidate for removal. A block that has never
fired is measured from the day it was added instead.

| Level | Applies when | Shown as | Default |
| --- | --- | --- | --- |
| New | up to the *New* day count | bold red | 15 days |
| Mature | up to the *Mature* day count | red | 30 days |
| Good | up to the *Good* day count | green | 60 days |
| Clear | anything older | bold green | — |

### Block count

The **Block Count** column is coloured by how many times the block has fired, whatever
the date says — a block that has stopped hundreds of requests is worth seeing even if
it has been quiet lately. Its thresholds are floors rather than ceilings, the opposite
way round to the day counts, because a high count is the bad end of this measure while
a high day count is the good end of that one.

| Level | Applies when | Shown as | Default |
| --- | --- | --- | --- |
| Normal | below the *Warning* count | unstyled | — |
| Warning | from the *Warning* count | red | 20 blocks |
| Severe | from the *Severe* count | bold red | 50 blocks |

### Settings

All five numbers are set under **Fuse CMS → Site Settings**, in the **IP Blocker**
panel, grouped as *Block age* and *Block count*. Each set has to climb, so if they are
set out of order each one is held at or above the one below it rather than the list
being allowed to go strange.

Developers can change the CSS classes with the `fuse_ipblocker_status_levels` and
`fuse_ipblocker_count_levels` filters. Each level also carries a `label`, which the
block list does not print but which is there for anything else that wants to name the
level.

## The block message

Set the `fuse_ipblocker_blockmessage` option to change what a blocked visitor sees.
There is no settings field for it yet, so it has to be set in code:

```php
update_option ('fuse_ipblocker_blockmessage', 'Access denied.');
```

The default is "You are not allowed to access this resource". The message is passed
through `wp_kses_post ()`, so basic markup is allowed but scripts are stripped.

## How the check runs

The check runs as the plugin file loads, before WordPress has finished starting up,
so a blocked request is stopped as early as possible. It first confirms the blocks
table exists, then looks for a prefix match against the visitor's `REMOTE_ADDR`. On a
match it increments the counter, writes a log row, prints the block message and stops
the request.

Two consequences worth knowing:

- The check adds two queries to **every** request, including ones that are not
  blocked.
- A blocked request is answered with HTTP 403 and no-cache headers.
- `REMOTE_ADDR` is the address the web server sees. Behind a reverse proxy, a CDN or
  a load balancer that is the proxy's address, not the visitor's, so blocks will not
  behave as expected without the proxy passing the real address through.

## Licence

© 7-90 Systems. See `LICENSE`.
