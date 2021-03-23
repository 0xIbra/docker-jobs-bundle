Docker Jobs Bundle
===================
> Symfony bundle that offers a **batch processing system** with **Docker**.

Uses Docker containers to run and handle your jobs.

* [Installation](#installation)
* [Configuratin](#configuration)
 * [Job Entity](#job-entity)
 * [Docker Configuration](#docker-configuration)
   * [Docker socket permission](#docker-socket-permission)
   * [Docker Image](#docker-image)
 * [Bundle Configuration](#bundle-configuration)
* [Next steps](#next-steps)
  * [Console commands](docs/console.md)
    * [Job orchestration command](docs/console.md#job-orchestration-command)
    * [Job submission command](docs/console.md#job-submission-command)
    * [Stop job command](docs/console.md#stop-job-command)
    * [Orphan job cleaning command](docs/console.md#clean-orphan-jobs-command)
  * [Job Monitoring Dashboard](docs/dashboard.md)

Installation
------------

    composer require polkovnik-z/docker-jobs-bundle


Configuration
-------------
The configuration process is a bit lengthy, follow all the steps shown below.

* [Job Entity](#job-entity)
* [Docker Configuration](#docker-configuration)
 * [Docker socket permission](#docker-socket-permission)
 * [Docker Image](#docker-image)
* [Bundle Configuration](#bundle-configuration)


--------------------------------------------------------------------------------

<br>

### Job Entity
You must create your Job entity and extend of BaseJob class.
```php
<?php

namespace App\Entity;

use Polkovnik\DockerJobsBundle\Entity\BaseJob;

class Job extends BaseJob
{
}

```
As long as you extend it of `BaseJob`, the bundle will work correctly.

--------------------------------------------------------------------------------


### Docker configuration
For this bundle to work, 2 actions you must perform.

If the **Docker socket permission** step is not completed,  
the bundle will throw an **exception** as it is not able to connect to **Docker**.

<br>

#### Docker socket permission

By default, in most cases, **PHP** does not have permission to access the docker socket
which by default on linux is located at `/var/run/docker.sock`.

For **PHP** to be able to bind to this socket and perform http requests,
you must give the necessary permissions.

**Easiest way**  
`sudo chmod 777 /var/run/docker.sock`  
By doing this, you give everyone read-write-execute permissions to this file to **everyone**.

**Hard way**  
If you are concerned with security, then you can give these permissions only to the **PHP** user.

<br>

#### Docker Image

If not already done, you must create a Docker image which will be used to execute your jobs.  

This bundle allows use of different images for each job but requires a default image to fallback on when no docker image is specified at launch.

--------------------------------------------------------------------------------

### Bundle Configuration
Once you've got your Job entity and Docker image ready, the last step is to configure the bundle.

If not already done, include the bundle to your project:
```php
<?php
# Symfony 4
# ./project_dir/config/bundles.php

return [
 Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
 ...
 Polkovnik\DockerJobsBundle\DockerJobsBundle::class => ['all' => true],
];

# Symfony 3
# ./project_dir/app/AppKernel.php

$bundles = array(
 new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
 new Symfony\Bundle\SecurityBundle\SecurityBundle(),
 ...
 new Polkovnik\DockerJobsBundle\DockerJobsBundle(),
)

```
**Yaml configuration**
```yaml
# Symfony 4
# ./project_dir/config/packages/docker_jobs.yaml

# Symfony 2 - 3
# ./project_dir/app/config/config.yml

docker_jobs:
 class:
   job: App\Entity\Job # Required

 docker:
   # This is the docker daemon socket
   # If you haven't touched the Docker config, it should be "/var/run/docker.sock" by default
   unix_socket_path: '/var/run/docker.sock' # Required

   # Default docker image ID for job execution
   # You can specify docker image when creating a job, this image will be used if no image is specified at creation.
   default_image_id: '6c39ebee77c9'         # Required

   # This parameter is required to avoid eventual errors
   # When starting a container for your job, the user will cd into this directory.
   container_working_dir: '/opt/symfony-project'        # Required

   # This parameter is used to convert docker container dates to my timezone.
   # Docker by default uses UTC+00:00 time, me who's residing in France, i need to add 1 hour to it for it to be correct (UTC+01:00).
   # You can use this parameter to add or remove hours to adapt it to your timezone.
   time_difference: '+1 hour' # Optional (default: +1 hour)
   # time_difference: '+3 hours'
   # time_difference: '-2 hours'

 runtime: # Optional
   # This is a default concurrency limit value
   # This value can be overridden in the job orchestrating command.
   concurrency_limit: 4 # Optional (default: 4)

```

At this point, you should have the bundle imported and working.  
Next you need to configure the **job orchestrating command** to run non-stop.  
Check out the steps below.

--------------------------------------------------------------------------------

Next steps
----------
- Check out the [Console commands](docs/console.md) to get the system up and running.  
  This step is vital to get the orchestration of job containers up and running.


- Check out the [Monitoring dashboard](docs/dashboard.md) that you can activate if you wish.
