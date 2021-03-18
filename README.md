 Docker Jobs Bundle
===================
> Symfony bundle that offers a **batch processing system** with **Docker**.

Uses Docker containers to run and handle your jobs.

* [Installation](#installation)
* [Configuratin](#configuration)
  * [Job Entity](#job-entity)
  * [Docker Image](#docker-image)
  * [Bundle Configuration](#bundle-configuration)
* [Next steps](#next-steps)
  * [Console commands](#docs/console.md)
  * [Job Monitoring Dashboard](#docs/dashboard.md)

Installation
------------

    composer require polkovnik/docker-jobs-bundle


Configuration
-------------
The configuration process is a bit lengthy, follow all the steps shown below.

* [Job Entity](#job-entity)
* [Docker Image](#docker-image)
* [Bundle Configuration](#bundle-configuration)



#### Job Entity
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


#### Docker image
If not already done, you must create a Docker image which will be used to execute your jobs.  

This bundle allows use of any image for each job but requires a default image to fallback on when no docker image is specified at launch.


#### Bundle Configuration
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

  runtime: # Optional
    # This is a default concurrency limit value
    # This value can be overridden in the job orchestrating command.
    concurrency_limit: 4r # Optional

```

At this point, you should be good, the configuration is done.  

Next steps
---------
 - Check out the [Console commands](docs/console.md) to get the system up and running.
 - Check out the [Monitoring dashboard](docs/dashboard.md) that you can activate if you wish.
