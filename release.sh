tag=$(git describe --tags)
tar -czvf metrodb-$tag.tar.gz  --transform 's,^,/metrodb/,' *.php README.md composer.json
#zip metrodb-$tag.zip  metrodb/*.php metrodb/README.md metrodb/composer.json
