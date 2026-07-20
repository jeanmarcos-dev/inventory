#!/bin/sh
# Convenience runner for dist-qa-local.sh on a host whose PHP cannot run the tools
# (for example a CLI PHP without ext-xml). It runs the portable script inside a container
# built from a Magento PHP image that provides ext-xml + composer, mounting the working tree
# (git-worktree aware) so git history and change detection work.
#
# Image resolution order:
#   1. $DIST_QA_IMAGE
#   2. the image of a running container whose name/image matches magento-php / php-noxdebug
#
# Usage:
#   .github/scripts/dist-qa-local-docker.sh [base-ref]
set -eu

TOP=$(git rev-parse --show-toplevel)
COMMON=$(cd "$(git rev-parse --git-common-dir)" && pwd -P)

# Mount a directory containing both the working tree and (for git worktrees) the shared .git.
MOUNT=$(dirname "$TOP")
case "$COMMON/" in
    "$MOUNT"/*) : ;;
    *) MOUNT="/" ;;
esac

IMAGE="${DIST_QA_IMAGE:-}"
if [ -z "$IMAGE" ]; then
    IMAGE=$(docker ps --format '{{.Image}}' 2>/dev/null | grep -iE 'magento-php|php-noxdebug' | head -n1 || true)
fi
if [ -z "$IMAGE" ]; then
    echo "No PHP image found. Set DIST_QA_IMAGE=<image with ext-xml + composer>." >&2
    exit 2
fi

echo "Running dist QA in $IMAGE (mount: $MOUNT)"
exec docker run --rm -v "$MOUNT":"$MOUNT" -w "$TOP" "$IMAGE" \
    sh -c 'git config --global --add safe.directory "*" >/dev/null 2>&1; exec sh .github/scripts/dist-qa-local.sh "$@"' _ "$@"
