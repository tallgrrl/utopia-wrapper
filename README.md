# utopia-wrapper
======

Utopia is a very light weight PHP framework specifically designed for Service Oriented Architecture.

Version 1 of Utopia is very light weight, and designed for an all-in-1 PHP framework that is easy to set up and use.

I used it on many projects, but sadly have not kept it maintained in the last few years as I left PHP for a while.

While I was away, I have designed this new way of building and depploying SOA architectures. This architecture is actiually Language agnostic, so I will be creating several language versions of Utopia.


Initial setup:

Make sure mod_rewrite is active
Make sure php_short_tags is on

checkout the wrapper into designated space:

>git clone https://github.com/tallgrrl/utopia-wrapper.git coolwebspace

cd into coolwebspace

>cd coolwebspace

create a new folder called 'services'

>mkdir services

cd into services

>cd services

checkout a copy of the service into this folder under the name of 'home'

>git clone https://github.com/tallgrrl/utopia-service.git home


in the wrapper ini folder, you will find a folder called serviceRegistry.ini
Here is a block of config defining the [home] service (whihc you just created) and the paths to the controllers


Needed  

Service level Auto Loading
