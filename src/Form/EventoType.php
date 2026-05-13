<?php

namespace App\Form;

use App\Entity\Evento;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

class EventoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nome', TextType::class, ['label' => 'Nome do Evento'])
            ->add('descricao', TextareaType::class, [
                'label' => 'Descrição',
                'required' => false,
                'attr' => ['class' => 'tinymce', 'rows' => 8],
            ])
            ->add('chavePix', TextType::class, ['label' => 'Chave Pix'])
            ->add('beneficiarioPix', TextType::class, [
                'label' => 'Nome do Beneficiário Pix',
                'required' => false,
            ])
            ->add('cidadePix', TextType::class, [
                'label' => 'Cidade do Beneficiário Pix',
                'required' => false,
            ])
            ->add('mensagemSucesso', TextareaType::class, [
                'label' => 'Mensagem de Sucesso',
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('bannerFile', VichImageType::class, [
                'label' => 'Banner do Evento',
                'required' => false,
                'allow_delete' => true,
                'download_uri' => false,
            ])
            ->add('logoFile', VichImageType::class, [
                'label' => 'Logo do Evento',
                'required' => false,
                'allow_delete' => true,
                'download_uri' => false,
            ])
            ->add('dataInicio', DateType::class, [
                'label' => 'Data de Início',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('dataFim', DateType::class, [
                'label' => 'Data de Fim',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => ['Ativo' => 'ativo', 'Inativo' => 'inativo'],
            ])
            ->add('corBackground', ColorType::class, [
                'label' => 'Cor do Background',
                'required' => false,
            ])
            ->add('corTexto', ColorType::class, [
                'label' => 'Cor Primária do Texto',
                'required' => false,
            ])
            ->add('corTextoSecundario', ColorType::class, [
                'label' => 'Cor Secundária do Texto',
                'required' => false,
            ])
            ->add('corBotaoPrimario', ColorType::class, [
                'label' => 'Cor dos Botões Primários',
                'required' => false,
            ])
            ->add('corBotaoSecundario', ColorType::class, [
                'label' => 'Cor dos Botões Secundários',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evento::class,
        ]);
    }
}
