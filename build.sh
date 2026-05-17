#!/bin/bash
set +x


echo "1 of 5) Removing old files";
#Remove the contents of previous distributions
rm -rf ./dist/eluminate-standalone/
rm -rf ./dist/*.zip

#Remove old build files
rm -rf ./build/eluminate-standalone/

echo "2 of 5) Installing dependencies";
# Install prod dependencies for NiztechYouTube project. Dependencies are not scoped at this point
composer install --no-dev --optimize-autoloader --classmap-authoritative --quiet

echo "3 of 5) Creating build/eluminate-standalone";
rsync -a \
  --exclude='.editorconfig*' \
  --exclude='.git*' \
  --exclude='.idea' \
  --exclude='_NOTES' \
  --exclude='build' \
  --exclude='build.sh' \
  --exclude='composer.*' \
  --exclude='dist' \
  --exclude='node_modules' \
  --exclude='tests' \
  ./ ./build/eluminate-standalone/

echo "4 of 5) Zipping";
cd ./build || exit

#bundle it up
timestamp=$(date +%s)
zip -rq ./eluminate-standalone-"$timestamp".zip ./eluminate-standalone

mv eluminate-standalone-"$timestamp".zip ../dist/

echo "5 of 5) Cleanup";
#remove build version of "niztech-youtube" folder
rm -rf ./eluminate-standalone/

echo "Done!";
echo "eluminate-standalone-"$timestamp".zip"
