# DictionarySupport
Dictionary support module for ProcessWire

## Work in progress
Please note that these modules are not finished and they are in continuous development as of October, 2017.  
Second note: PW 3.0.62 has a bug and needs manual fix for conditional hooks.
See [this issue](https://github.com/processwire/processwire-issues/issues/261) for a fix or upgrade to the latest dev.

## Purpose
These modules provides support for importing, storing, displaying and using dictionary entries in ProcessWire.  
They can handle a large number of headwords (tested with 15k+ entries) with low memory requirements. Longer tasks are run using the [Tasker](https://github.com/mtwebit/Tasker) module.  
The software was developed for the [Mikes-dictionary project].

## Installation
First, ensure that your ProcessWire installation and the underlying database meets the encoding and indexing requirements of your projects. Their default settings probably won't work for you if you store materials in languages other than English. See [Encoding.md](https://github.com/mtwebit/DictionarySupport/blob/master/Encoding.md) for more details.  
After creating your ProcessWire site, install the module the usual way and follow the instructions on the module's home page.

## How to use
TODO

## License
The "github-version" of the software is licensed under MPL 2.0  
