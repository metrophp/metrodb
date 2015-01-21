rm -f metrodb
ln -s . metrodb
tag=$(git describe --tags)
zip metrodb-$tag.zip  metrodb/*.php metrodb/nosql/mongo.php metrodb/README.md metrodb/composer.json
