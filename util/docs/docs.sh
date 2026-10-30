#!/usr/bin/env bash
#  Builds or serves the documentation with MkDocs (Material theme).
#  MkDocs is taken from PATH, from the Python venv in util/docs/.venv or from Docker.
#  Usage: util/docs/docs.sh install|build|serve
set -euo pipefail
export NO_MKDOCS_2_WARNING=1
cd "$(dirname "$0")/../.."

VENV="util/docs/.venv"
IMAGE="squidfunk/mkdocs-material:9"
PORT="${DOCS_PORT:-8000}"
CONFIG="util/mkdocs.yml"

run_mkdocs() {
	local arguments=(--config-file "$CONFIG")
	[ "$1" = "serve" ] && arguments+=(--dev-addr "127.0.0.1:$PORT")
	if [ -x "$VENV/bin/mkdocs" ]; then
		"$VENV/bin/mkdocs" "$@" "${arguments[@]}"
	elif command -v mkdocs >/dev/null 2>&1 && python3 -c "import material" >/dev/null 2>&1; then
		mkdocs "$@" "${arguments[@]}"
	elif command -v docker >/dev/null 2>&1; then
		local options=(--rm -e NO_MKDOCS_2_WARNING=1 -u "$(id -u):$(id -g)" -v "$PWD:/docs")
		[ -t 0 ] && options+=(-it)
		[ "$1" = "serve" ] && options+=(-p "127.0.0.1:$PORT:8000") && arguments=(--config-file "$CONFIG" --dev-addr "0.0.0.0:8000")
		docker run "${options[@]}" "$IMAGE" "$@" "${arguments[@]}"
	else
		echo "MkDocs not found: install mkdocs-material, run 'composer dev:docs-install' or install Docker" >&2
		exit 1
	fi
}

case "${1:-build}" in
	install)
		python3 -m venv "$VENV"
		"$VENV/bin/pip" install --quiet --upgrade pip
		"$VENV/bin/pip" install --quiet -r util/docs/requirements.txt
		;;
	build)
		run_mkdocs build --strict
		;;
	serve)
		echo "Documentation: http://localhost:$PORT"
		run_mkdocs serve
		;;
	*)
		echo "Usage: $0 install|build|serve" >&2
		exit 1
		;;
esac
