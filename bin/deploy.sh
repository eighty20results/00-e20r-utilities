#!/bin/bash
#
# Send plugin kit to the specified remote server
# Copyright 2021 sjolshag

function main() {
	declare metadata
	declare remote_path
	declare src_path
	declare dst_path
	declare plugin_path
	declare kit_path
	declare kit_name
	declare remote_server

	source build_config/helper_config "${@}"

	src_path="$(pwd)"
	plugin_path="${short_name}"
	dst_path="${src_path}/build/${plugin_path}"

	kit_path="${src_path}/build/kits"
	kit_name="${kit_path}/${short_name}-${version}.zip"
	remote_server="${E20R_SSH_USER}@${E20R_SSH_SERVER}:${E20R_SSH_PORT}"
	remote_path="./www/eighty20results.com/public_html/protected-content/"

	metadata="${src_path}/metadata.json"

	# We _want_ to expand the variables on the client side
	# shellcheck disable=SC2029
	ssh "${remote_server}" "cd ${remote_path}; mkdir -p \"${short_name}\""

	echo "Copying ${kit_name} to ${remote_server}:${remote_path}/${short_name}/"
	scp "${kit_name}" "${remote_server}:${remote_path}/${short_name}/"

	echo "Copying ${metadata} to ${remote_server}:${remote_path}/${short_name}/"
	scp "${metadata}" "${remote_server}:${remote_path}/${short_name}/"

	echo "Linking ${short_name}/${short_name}-${version}.zip to ${short_name}.zip on remote server"
	# We _want_ to expand the variables on the client side
	# shellcheck disable=SC2029
	ssh "${remote_server}" \
		"cd ${remote_path}/ ; ln -sf \"${short_name}\"/\"${short_name}\"-\"${version}\".zip \"${short_name}\".zip"

	# Return to the root directory
	cd "${src_path}" || die 1

	# And clean up
	rm -rf "${dst_path}"
}

main "$@"
