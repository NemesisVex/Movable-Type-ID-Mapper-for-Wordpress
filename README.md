# Movable Type ID Mapper

## Overview

Movable Type ID Mapper maps custom Movable Type URLs to corresponding WordPress entries that have been imported from a Movable Type blog. The mapper parses a URL for an entry ID, then queries a Movable Type ``mt_entry`` table for a post. If a post is found, the ``entry_basename`` field is used to query WordPress, and a successful match redirects to that post.

A theme registers at least one regular expression to the mapper, indicating an offset where the entry ID may be found. The mapper checks for pages but ignores posts and attachments, as well as all archive, category and author URLs.

An administrator may specify an external database where Movable Type may be hosted. If none is configured, the WordPress database is used.

## Usage

### Registering a URL pattern in your theme

In the ``functions.php`` file of your theme, add a function similar to the following example:

		function theme_register_mt_id_patterns() {
			if ( function_exists( 'mt_id_mapper_register_pattern' ) )  {
				mt_id_mapper_register_pattern( array(
					// Match on /blog/entry/ID or just /entry/ID
					'pattern' => "/^\/(blog\/|)entry\/([0-9]+)/",
					'offset' => 2
					)
				);
				mt_id_mapper_register_pattern( array(
					// Match on /entry.php?entry_id=ID
					'pattern' => "/entry\.php\?entry_id=([0-9]+)/",
					'offset' => 1
					)
				);
			}
		}
		add_action( 'mt_id_mapper_pattern_setup', 'theme_register_mt_id_patterns' );
