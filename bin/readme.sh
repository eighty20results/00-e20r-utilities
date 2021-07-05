#!/usr/bin/env bash
sed="$(which sed)"
readme_path="./build_readmes/"
wordpress_version=$(wget -q -O - http://api.wordpress.org/core/stable-check/1.0/  | grep latest | awk '{ print $1 }' | sed -e 's/"//g')
# stripped_log=$(mktemp /tmp/old-info-XXXXXX)
version=$(grep -E "^Version:" ./class-loader.php | sed 's/[[:alpha:]|(|[:space:]|\:]//g' | awk -F- '{printf "%s", $1}')

###########
#
# Update plugin and wordpress version info in README.txt
#
if [[ -f ./README.txt ]]; then
	echo "Updating the README.txt file"
	"${sed}" -r -e "s/Stable tag: ([0-9]+\.[0-9].*)/Stable\ tag:\ ${version}/g" \
	 				 -e "s/^Tested up to: ([0-9]+\.[0-9].*)/Tested up to: ${wordpress_version}/g"\
	 				 ./README.txt > ./NEW_README.txt
	mv ./NEW_README.txt ./README.txt
	cp ./README.txt ./README.md
	echo "Generating the README.md file"
	"${sed}" -r -e "s/^\= (.*) \=/## \1/g" \
					 -e "s/^\=\= (.*) \=\=/### \1/g" \
					 -e "s/^\=\=\= (.*) \=\=\=/### \1/g" \
					 -e "s/^\* (.*)$/- \1/g" \
					 -e "s/^([A-zA-Z ]*): ([A-zA-Z0-9\.\,\\\/: ]*)/\`\1\: \2\` <br \/>/g" \
					 ./README.md > NEW_README.md
	mv ./NEW_README.md ./README.md
fi

git commit -m "BUG FIX: Updated README info (v${version} for WP ${wordpress_version})" ./README.txt ./README.md
