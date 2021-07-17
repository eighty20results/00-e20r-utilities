#!/usr/bin/env bash
#
# Send plugin kit to the specified remote server
#
# Copyright 2021 Eighty/20 Results by Wicked Strong Chicks, LLC
#
function main() {
	declare metadata
	declare remote_path
	declare src_path
	declare dst_path
	declare plugin_path
	declare kit_path
	declare kit_name
	declare remote_server
	declare target_server
	declare ssh_port
	declare ssh_user
	declare ssh_host

	source build_config/helper_config "${@}"

	src_path="$(pwd)"
	plugin_path="${short_name}"
	dst_path="${src_path}/build/${plugin_path}"
	ssh_port="22"
	ssh_host="${remote_server}"
	ssh_user="$(id -un)"

	kit_path="${src_path}/build/kits"
	kit_name="${kit_path}/${short_name}-${version}.zip"

	if [ -n "${E20R_SSH_USER}" ]; then
		ssh_user="${E20R_SSH_USER}"
	fi

	if [ -n "${E20R_SSH_HOST}" ]; then
		ssh_host="${E20R_SSH_HOST}"
	fi

	if [ -n "${E20R_SSH_PORT}" ]; then
		ssh_port="${E20R_SSH_PORT}"
	fi

	target_server="${ssh_user}@${ssh_host}"
	remote_path="./www/eighty20results.com/public_html/protected-content/"
	metadata="${src_path}/metadata.json"

	# We _want_ to expand the variables on the client side
	# shellcheck disable=SC2029
	ssh -o StrictHostKeyChecking=no -v -p "${ssh_port}" "${target_server}" "cd ${remote_path}; mkdir -p \"${short_name}\""

	echo "Copying ${kit_name} to ${remote_server}:${remote_path}/${short_name}/"
	scp -o StrictHostKeyChecking=no -v -P "${ssh_port}" "${kit_name}" "${target_server}:${remote_path}/${short_name}/"

	echo "Copying ${metadata} to ${remote_server}:${remote_path}/${short_name}/"
	scp -o StrictHostKeyChecking=no -v -P "${ssh_port}" "${metadata}" "${target_server}:${remote_path}/${short_name}/"

	echo "Linking ${short_name}/${short_name}-${version}.zip to ${short_name}.zip on remote server"

	# We _want_ to expand the variables on the client side
	# shellcheck disable=SC2029
	ssh -o StrictHostKeyChecking=no -v -p "${ssh_port}" "${target_server}" \
		"cd ${remote_path}/ ; ln -sf \"${short_name}\"/\"${short_name}\"-\"${version}\".zip \"${short_name}\".zip"

	# Return to the root directory
	cd "${src_path}" || die 1

	# And clean up
	rm -rf "${dst_path}"
}

main "$@"
