# Netric
This is the source code for both the client and the server.

## Running Local Development Server

1. Install docker native (not docker toolbox)

    http://www.docker.com/products/overview

2. Build the environment
    
    ./server/build-dev.sh
    
3. Run dev environment

    ./start-dev.sh
    
4. Load netric in the browser

    http://devel.netric.com

That's all there is to it, you should now be able to navigate to 
devel.netric.com on your workstation.

Log in with "test@netric.com" and "password" as the password.

### Testing
Run ./test.sh [optargs]
You can pass any arguments after test.sh that you would to phpunit. For example,
if you only want to test a specific test type:

    ./test.sh NetricTest/Application/Application.php

### Accessing Services
- devel.netric.com:80 - load netric
- devel.netric.com:5601 - load kibana to view logs
- devel.netric.com:5432 - connect to postgres with user vagrant and password vagrant

### Debugging
If using PHPStorm simply start listening on port 9000 for xdebug

### Remote In

You can remote into the server with the follwoing command:

    docker exec -it docker_netric_server_1 /bin/bash
    
This will spawn a new interactive process in the container and run bash which
is similar what happens when you SSH into a remote machine.

## The Client

Netric can have multiple clients. The main web client is build separately and deployed by
copying the source from the built webapp into the server directory /server/v2. When we finish
moving the server into a new directory structure we will simply deploy the built
webapp into /public/*.

## Clients are split into separate repos:
netric-client-web: The main webapp
netric-client-hybrid: Native device applications

Ecah of the respective client repos will contain build and deployment instructions.

## Scripts found in /scripts

build-dev.sh - build a local development environment
start-dev.sh - start a local development server
test.sh - run server tests

jenkins-tests.sh - script to execute all tests in jenkins build

# Work-in-Progress

We are working on simplifying the directory structure of netric. When finished the root should 
look like:

/src - all netric classes
/public - all served assets and where apache will look for index files
/vendor - libraries
/data - non-executable stored data
/bin - binary scripts to run on the server
/scripts - development scripts
/docker - docker files used for building and working with containers