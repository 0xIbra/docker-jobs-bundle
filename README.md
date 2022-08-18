Docker Jobs Bundle
===================
> Symfony bundle that offers a **batch processing system** with **Docker**.

Uses Docker containers to run and handle your jobs.

* [Need to know](#need-to-know)
* [Docker Configuration](#docker-configuration)
* [Installation](#installation)
* [Configuration](#configuration)
 * [Job Entity](#job-entity)
 * [Bundle Configuration](#bundle-configuration)
* [Next steps](#next-steps)
  * [Console commands](docs/console.md)
    * [Job orchestration command](docs/console.md#job-orchestration-command)
    * [Job submission command](docs/console.md#job-submission-command)
    * [Stop job command](docs/console.md#stop-job-command)
    * [Orphan job cleaning command](docs/console.md#clean-orphan-jobs-command)
  * [Job Monitoring Dashboard](docs/dashboard.md)


Need to know
-------------------
All containers started by this bundle, are by default on the host's network.  
So, if you need to connect to a local database, you can with the usual `localhost|127.0.0.1`.


Docker configuration
--------------------
Docker Engine API must be exposed on a local port in order to be able to connect.

##### 1. Edit the `docker.service` which by default on debian is located at `/lib/systemd/system/docker.service`

From this:
```shell
# /lib/systemd/system/docker.service
...
ExecStart=/usr/bin/dockerd -H fd:// --containerd=/run/containerd/containerd.sock
...
```

To this:
```shell
# /lib/systemd/system/docker.service
...
ExecStart=/usr/bin/dockerd
...
```

##### 2. Edit `/etc/docker/daemon.json` to expose docker api at `127.0.0.1:2375`
Add `hosts` to the json file as next:
```json
{
  ...
  "hosts": ["fd://", "tcp://127.0.0.1:2375"]
  ...
}
```

##### 3. Restart Docker completely
```shell
systemctl daemon-reload
systemctl restart docker
service docker restart
```

#### Docker Image

If not already done, you must create a Docker image which will be used to execute your jobs.

This bundle allows use of different images for each job but requires a default image to fallback on when no docker image is specified at launch.


Installation
------------

##### Install the bundle

    composer require ibra-akv/docker-jobs-bundle


Configuration
-------------
The configuration process is a bit lengthy, follow all the steps shown below.

* [Job Entity](#job-entity)
* [Bundle Configuration](#bundle-configuration)


--------------------------------------------------------------------------------

<br>

### Job Entity
You must create your Job entity and extend of BaseJob class.
```php
<?php

namespace App\Entity;

use IterativeCode\DockerJobsBundle\Entity\BaseJob;

class Job extends BaseJob
{
}

```
As long as you extend it of `BaseJob`, the bundle will work correctly.

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
 IterativeCode\DockerJobsBundle\DockerJobsBundle::class => ['all' => true],
];

# Symfony 3
# ./project_dir/app/AppKernel.php

$bundles = array(
 new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
 new Symfony\Bundle\SecurityBundle\SecurityBundle(),
 ...
 new IterativeCode\DockerJobsBundle\DockerJobsBundle(),
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
   # The URI where Docker Engine API is exposed
   # The bundle will use this endpoint to communicate with Docker
   docker_api_endpoint: 'http://127.0.0.1:2375' # optional (default: http://localhost:2375)

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

--------------------------------------------------------------------------------

License
-------

 - [Review](LICENSE)
