#!/bin/bash
set -euo pipefail
DIR_BASE="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

TEST_GROUP=$1
echo "Test group is $TEST_GROUP"
PATCH_DIR="$DIR_BASE/$TEST_GROUP"
echo "Looking for patches in $PATCH_DIR"

if ! test -d $PATCH_DIR; then
  exit 0
fi

for i in `find $PATCH_DIR -name '*.patch'`; do
  echo "Applying $i"
  patch -p1 < $i
done
