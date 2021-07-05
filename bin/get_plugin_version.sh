#!/bin/bash
plugin_shortname="${1}"
grep -E "^Version:" "class-${plugin_shortname}.php" | \
	sed 's/[[:alpha:]|(|[:space:]|\:]//g' | \
	awk -F- '{printf "%s", $1}'
