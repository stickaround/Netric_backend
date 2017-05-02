# Netric
This is the source code for both the client and the server.

## Running Local Development Server

1. Install docker native (not docker toolbox)

    http://www.docker.com/products/overview

2. Build the environment (in linux/mac)
    
    python build-dev.py
    
3. Run dev environment (in linux/mac)

    python start-dev.py
    
4. Load netric in the browser

    http://127.0.0.1

That's all there is to it, you should now be able to navigate to 
http://127.0.0.1 on your workstation.

Log in with "test@netric.com" and "password" as the password.

### Testing
Run python test.py [optargs]
You can pass any arguments after test.sh that you would to phpunit. For example,
if you only want to test a specific test type:

    python test.py NetricTest/Application/Application.php

### Accessing Services
- http://127.0.0.1:80 - load netric
- http://127.0.0.1:5601 - load kibana to view logs
- localhost:5432 - connect to postgres with user vagrant and password vagrant

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

Note: Make sure you have python 3+ installed and working

	python build-dev.py # build a local development environment
	python start-dev.py # start a local development server
	python test.py # run server tests

	./jenkins-tests.sh # script to execute all tests in jenkins build

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