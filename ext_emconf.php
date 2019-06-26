{
 "scripts": {
  "post-autoload-dump": [
   "@typo3-cms-scripts"],
  "typo3-cms-scripts": [
   "typo3cms install:fixfolderstructure","typo3cms install:generatepackagestates"]
 },
 "repositories": [
  {
   "url": "https://composer.typo3.org/",
   "type": "composer"
  }],
 "require": {
  "typo3/cms-adminpanel": "^9.5",
  "fluidtypo3/vhs": "^5.2",
  "typo3/cms-setup": "^9.5",
  "typo3/cms-impexp": "^9.5",
  "mediadreams/md_newsfrontend": "^1.1",
  "typo3/cms-belog": "^9.5",
  "typo3/cms-tstemplate": "^9.5",
  "apache-solr-for-typo3/solr": "^9.0",
  "typo3/cms-about": "^9.5",
  "typo3/cms-reports": "^9.5",
  "typo3/cms-seo": "^9.5",
  "typo3/cms-info": "^9.5",
  "typo3/cms-form": "^9.5",
  "typo3/minimal": "^9.5",
  "typo3/cms-sys-note": "^9.5",
  "in2code/femanager": "^5.1",
  "typo3/cms-t3editor": "^9.5",
  "helhum/typo3-console": "^5.6",
  "typo3/cms-fluid-styled-content": "^9.5",
  "typo3/cms-redirects": "^9.5",
  "typo3/cms-viewpage": "^9.5",
  "phperix/fe_user_cards": "^1.0",
  "typo3/cms-rte-ckeditor": "^9.5",
  "bk2k/bootstrap-package": "^10.0",
  "typo3/cms-beuser": "^9.5",
  "typo3/cms-felogin": "^9.5"
 },
 "description": "TYPO3 CMS Base Distribution",
 "config": {
  "platform": {
   "php": "7.2"
  }
 },
 "license": "GPL-2.0-or-later",
 "name": "typo3/cms-base-distribution"
}