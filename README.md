#ownCloud custom groups support

This apps makes it possible for users to create their own custom groups and manage their members.
It is then possible to share files or folders with these groups.

## QA metrics on master branch:

[![Build Status](https://travis-ci.org/owncloud/customgroups.svg?branch=master)](https://travis-ci.org/owncloud/customgroups/branches)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/owncloud/customgroups/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/owncloud/customgroups/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/owncloud/customgroups/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/owncloud/customgroups/?branch=master)

## Requirements

* ownCloud 10.0

## Building the app

* Make sure you have [Node JS](https://nodejs.org/) installed
* Run `make` and find the tarball in the "build" directory

## Install

* Extract the resulting tarball in the "apps" folder in ownCloud 

## Usage

* Login as a regular user
* Go to the settings page
* Create custom group and add other users as members
* Share file/folder with said groups

## Developing

* Run `make help` to get information about the different targets.

## Authors:

[Vincent Petry](https://github.com/PVince81/) :: PVince81 at owncloud dot com

