composer install --no-dev
rm -r composer.json
rm -r composer.lock

rm -r README.md

rm -rf .idea/
rm -rf .git/
rm -r .editorconfig
rm -r .gitignore

rm -r phpcs.xml
rm -r .prettierrc
rm -r .eslintignore
rm -r .eslintrc.json

echo "Production Ready 📦"
rm -r clean.sh
