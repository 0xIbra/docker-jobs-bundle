<?php

namespace Polkovnik\DockerJobsBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SubmitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $dockerImageOptions = [];
        if (!empty($options['dockerImageId'])) {
            $dockerImageOptions['data'] = $options['dockerImageId'];
        }

        $builder
            ->add('command', TextareaType::class, [
                'label' => 'polkovnik.docker_jobs.fields.command.label',
                'translation_domain' => 'DockerJobsBundle',
            ])
            ->add('queue', TextType::class, [
                'label' => 'polkovnik.docker_jobs.queue',
                'translation_domain' => 'DockerJobsBundle',
            ])
            ->add('dockerImageId', TextType::class, [
                'data' => $options['dockerImageId'],
                'label' => 'polkovnik.docker_jobs.image.label',
                'translation_domain' => 'DockerJobsBundle',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['dockerImageId' => null]);
    }
}
