# TYPO3 Extension: Geocoding

Provides services for google maps GeoCoding API. Let's you fetch all 

> Extension Key: geocoding  
> Author: b:dreizehn GmbH, 2012  
> Licensed under: GPL 2+  
> Required TYPO3 4.5+ with the caching framework enabled  

## Introduction
This extension provides an abstract way to get geo coordinates of addresses around the world.

## Configuration
Fetch a google API key and add it to the extension configuration.

## How to use
When developing your own extension, make sure to add fields "latitude" and "longitude" of type float. Then, use the Service classes with integrated caching to fetch the coordinates of certain addresses.