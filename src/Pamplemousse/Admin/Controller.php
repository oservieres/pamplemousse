<?php
namespace Pamplemousse\Admin;

use Pamplemousse\Photos\Entity\Photo;
use Pamplemousse\Photos\Form\Type\PhotoType;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

use Upload\Storage\FileSystem;
use Upload\File;
use Upload\Validation\Mimetype;
use Upload\Validation\Size;

class Controller
{

    /**
     * @param  Application $app
     * @param  Request     $request
     * @return Response
     */
    public function indexAction(Application $app, Request $request)
    {
        return $app['twig']->render('admin/index.twig', [
            'photos' => $app['photos']->getAll()
        ]);
    }

    /**
     * @param  Application $app
     * @param  Request     $request
     * @return Response
     */
    public function loginCheckAction(Application $app, Request $request)
    {
        return true;
    }

    /**
     * @param  Application $app
     * @param  Request     $request
     * @return Response
     */
    public function logoutAction(Application $app, Request $request)
    {
        return true;
    }

    /**
     * @param  Application $app
     * @param  Request     $request
     * @param  [Photos]    $photos
     * @return Response
     */
    public function editAction(Application $app, Request $request, $photos)
    {
        $form = $app['form.factory']->createBuilder(FormType::class)
            ->add('photos', CollectionType::class, [
                'entry_type' => PhotoType::class,
                'data' => $photos
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            foreach ($photos as $id => $photo) {
                $app['photos']->update($photo);
            }
            return $app->redirect($app['url_generator']->generate('admin'));
        }

        if ($request->get('modal')) {
            $template = 'admin/modal/edit.twig';
        } else {
            $template = 'admin/edit.twig';
        }

        return $app['twig']->render($template, [
            'form' => $form->createView()
        ]);
    }

    /**
     * @param  Application $app
     * @param  Request     $request
     * @param  [Photos]    $photos
     * @return Response
     */
    public function cropAction(Application $app, Request $request, Photo $photo)
    {
        if (!$photo) {
            return $app->abort(404);
        }

        $thumbnail = $app['photos']->getThumbnail($photo, 200, 200, \Pamplemousse\Photos\Service::CROP_ENTROPY);
        $thumbnail = $app['photos']->getThumbnail($photo, 200, 200, \Pamplemousse\Photos\Service::CROP_BALANCED);
        $thumbnail = $app['photos']->getThumbnail($photo, 200, 200, \Pamplemousse\Photos\Service::CROP_CENTER);

        return $app['twig']->render('admin/crop.twig', [
            'photo' => $photo
        ]);
    }

    public function deleteAction(Application $app, Request $request, Photo $photo)
    {
        if (!$photo) {
            return $app->abort(404);
        }

        $app['photos']->delete($photo);
        return $app->redirect($app['url_generator']->generate('admin'));
    }

    /**
     * @param  Application $app
     * @param  Request     $request
     * @return Response
     */
    public function fileUploadAction(Application $app, Request $request)
    {
        $uploadDir = __DIR__.'/../../../web' . $app['config']['upload_dir'];
        $file = new File('file', new FileSystem($uploadDir));
        $file->addValidations(array(
            new Mimetype(array('image/png', 'image/gif', 'image/jpeg')),
            new Size('5M')
        ));

        // Upload file to server
        try {
            $file->setName($app['slug']->slugify($file->getName()));
            $file->upload();
            $app['monolog']->addDebug(sprintf("File uploaded: %s", json_encode($file->getNameWithExtension())));
        } catch (\Exception $exception) {
            $errorMessage = join('<br />', $file->getErrors());
            $app['monolog']->addError(sprintf("Error during file upload: %s", $errorMessage));
            return new Response(sprintf("Erreur : %s", $errorMessage), 400);
        }

        // Save file to db
        try {
            $photo = $app['photos']->add($file->getNameWithExtension());
        } catch (\Exception $exception) {
            $app['monolog']->addError(sprintf("Error during file insertion: %s", $exception->getMessage()));
            // Remove file from upload dir
            unlink($uploadDir.$file->getNameWithExtension());
            return new Response(sprintf("Erreur : %s", $exception->getMessage()), 400);
        }

        // Generate thumbnails
        $app['photos']->generateThumbnails($photo);

        return new Response($photo->id);
    }

}
