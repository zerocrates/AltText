<?php
namespace AltText;

use Doctrine\ORM\Events;
use AltText\Db\Event\Listener\DetachOrphanMappings;
use AltText\Entity\AltText as AltTextEntity;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Entity\Media as MediaEntity;
use Omeka\Form\Element\PropertySelect;
use Omeka\Module\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Form\Form;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $em->getEventManager()->addEventListener(
            Events::preFlush,
            new DetachOrphanMappings
        );
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $conn = $serviceLocator->get('Omeka\Connection');
        $conn->exec('CREATE TABLE alt_text (id INT AUTO_INCREMENT NOT NULL, media_id INT NOT NULL, alt_text LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_54A36CBEA9FDD75 (media_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $conn->exec('ALTER TABLE alt_text ADD CONSTRAINT FK_54A36CBEA9FDD75 FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE');
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $conn = $serviceLocator->get('Omeka\Connection');
        $conn->exec('DROP TABLE IF EXISTS alt_text');
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(Form::class);
        $form->add([
            'type' => PropertySelect::class,
            'name' => 'alt_text_property',
            'options' => [
                'label' => 'Alt text property', // @translate
                'info' => 'Media property to use as alt text. Used only if no alt text is explicitly set for a media.', // @translate
                'empty_option' => '[None]', // @translate
                'term_as_value' => true,
            ],
            'attributes' => [
                'id' => 'alt_text_fallback_property',
                'class' => 'chosen-select',
                'value' => $settings->get('alt_text_property'),
            ],
        ]);
        return $renderer->formCollection($form, false);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $property = $controller->params()->fromPost('alt_text_property');
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('alt_text_property', $property);
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            '*',
            'view_helper.thumbnail.attribs',
            function (Event $event) {
                $media = $event->getParam('primaryMedia');
                if (!$media) {
                    return;
                }

                $attribs = $event->getParam('attribs');
                if (!empty($attribs['alt'])) {
                    return;
                }

                $alt = null;
                $altText = $this->getAltTextForMedia($media);
                if ($altText) {
                    $alt = $altText->getAltText();
                }

                if (!strlen($alt)) {
                    $altValue = $this->getAltTextFallback($media);
                    $alt = $altValue ? (string) $altValue : null;
                }

                if ($alt !== null) {
                    $attribs['alt'] = $alt;
                }
                $event->setParam('attribs', $attribs);
            }
        );
        $sharedEventManager->attach(
            '*',
            'api.context',
            function (Event $event) {
                $context = $event->getParam('context');
                $context['o-module-alt-text'] = 'http://omeka.org/s/vocabs/module/alt-text#';
                $event->setParam('context', $context);
            }
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.edit.section_nav',
            [$this, 'addAltTextTab']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.edit.form.after',
            function (Event $event) {
                echo $event->getTarget()->partial('common/alt-text-form');
            }
        );
        $sharedEventManager->attach(
            'Omeka\Api\Representation\MediaRepresentation',
            'rep.resource.json',
            [$this, 'filterMediaJsonLd']
        );
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\MediaAdapter',
            'api.hydrate.post',
            [$this, 'hydrateAltText']
        );
    }

    /**
     * Get the AltText entity for a given media
     *
     * @param MediaRepresentation|MediaEntity $media
     * @return AltTextEntity
     */
    public function getAltTextForMedia($media)
    {
        if ($media instanceof MediaRepresentation) {
            $mediaId = $media->id();
        } elseif ($media instanceof MediaEntity) {
            $mediaId = $media->getId();
        } else {
            throw new \InvalidArgumentException('Unexpected argument type.');
        }

        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $dql = 'SELECT alt FROM AltText\Entity\AltText alt WHERE alt.media = ?1';
        $query = $entityManager->createQuery($dql)->setParameter(1, $mediaId);
        return $query->getOneOrNullResult();
    }

    public function getAltTextFallback(MediaRepresentation $media)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $property = $settings->get('alt_text_property');
        if (!$property) {
            return null;
        }
        return $media->value($property);
    }

    /**
     * Add the alt text data to the media JSON-LD.
     */
    public function filterMediaJsonLd(Event $event)
    {
        $altTextForJsonLd = null;
        $media = $event->getTarget();
        $jsonLd = $event->getParam('jsonLd');
        $altText = $this->getAltTextForMedia($media);
        if ($altText) {
            $altTextForJsonLd = $altText->getAltText();
        }
        $jsonLd['o-module-alt-text:alt-text'] = $altTextForJsonLd;
        $event->setParam('jsonLd', $jsonLd);
    }
    /**
     * Add the alt text tab to section nav.
     */
    public function addAltTextTab(Event $event)
    {
        $view = $event->getTarget();
        $sectionNav = $event->getParam('section_nav');
        $sectionNav['alt-text-section'] = $view->translate('Alt Text');
        $event->setParam('section_nav', $sectionNav);
    }

    /**
     * Hydrate alt text from media API requests.
     */
    public function hydrateAltText(Event $event)
    {
        $mediaAdapter = $event->getTarget();
        $request = $event->getParam('request');

        if (!$mediaAdapter->shouldHydrate($request, 'o-module-alt-text:alt-text')) {
            return;
        }

        $media = $event->getParam('entity');
        $altText = $this->getAltTextForMedia($media);
        $requestAltText = $request->getValue('o-module-alt-text:alt-text', '');

        if (!$altText) {
            if ($requestAltText === '') {
                return;
            }
            $altText = new AltTextEntity;
            $altText->setMedia($media);
            $this->getServiceLocator()->get('Omeka\EntityManager')->persist($altText);
        }

        $altText->setAltText($requestAltText);
    }
}
