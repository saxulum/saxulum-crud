<?php

namespace Saxulum\Crud\Controller;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use Knp\Component\Pager\PaginatorInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Saxulum\Crud\Exception\ServiceNotFoundException;
use Saxulum\Crud\Repository\QueryBuilderForFilterFormInterface;
use Saxulum\Crud\Util\Helper;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\SecurityContextInterface;

trait CrudTrait
{
    /**
     * @param Request $request
     * @param array   $templateVars
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function crudListObjects(Request $request, array $templateVars = array())
    {
        if (!$this->crudListIsGranted()) {
            throw new AccessDeniedException('You need the permission to list entities!');
        }

        if (null !== $formType = $this->crudListFormType()) {
            $form = $this->crudForm($formType, array());
            $form->handleRequest($request);
            $formData = $form->getData();
        } else {
            $formData = array();
        }

        $formData = array_replace_recursive($formData, $this->crudListFormDataEnrich());

        $repo = $this->crudRepositoryForClass($this->crudObjectClass());
        if (!$repo instanceof QueryBuilderForFilterFormInterface) {
            throw new \Exception(sprintf('A repo used for crudListObjects needs to implement: %s', QueryBuilderForFilterFormInterface::interfacename));
        }

        $qb = $repo->getQueryBuilderForFilterForm($formData);

        $pagination = $this->crudPaginate($qb, $request);

        $baseTemplateVars = array(
            'request' => $request,
            'pagination' => $pagination,
            'form' => isset($form) ? $form->createView() : null,
            'listRoute' => $this->crudListRoute(),
            'createRoute' => $this->crudCreateRoute(),
            'editRoute' => $this->crudEditRoute(),
            'viewRoute' => $this->crudViewRoute(),
            'deleteRoute' => $this->crudDeleteRoute(),
            'listRole' => $this->crudListRole(),
            'createRole' => $this->crudCreateRole(),
            'editRole' => $this->crudEditRole(),
            'viewRole' => $this->crudViewRole(),
            'deleteRole' => $this->crudDeleteRole(),
            'identifier' => $this->crudIdentifier(),
            'transPrefix' => $this->crudTransPrefix(),
            'objectClass' => $this->crudObjectClass(),
        );

        return $this->crudRender(
            $this->crudListTemplate(),
            array_replace_recursive($baseTemplateVars, $templateVars)
        );
    }

    /**
     * @param Request $request
     * @param array   $templateVars
     *
     * @return Response|RedirectResponse
     */
    public function crudCreateObject(Request $request, array $templateVars = array())
    {
        if (!$this->crudCreateIsGranted()) {
            throw new AccessDeniedException('You need the permission to create an object!');
        }

        $object = $this->crudCreateFactory();
        $form = $this->crudForm($this->crudCreateFormType($object), $object);

        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($this->crudCreateIsSubmitted($form)) {
                if ($form->isValid()) {
                    $this->crudCreatePrePersist($object);

                    $em = $this->crudManagerForClass($this->crudObjectClass());
                    $em->persist($object);
                    $em->flush();

                    $this->crudCreatePostFlush($object);

                    $this->crudFlashMessage($request, 'success', sprintf('%s.create.flash.success', $this->crudTransPrefix()));

                    return new RedirectResponse($this->crudCreateRedirectUrl($object), 302);
                } else {
                    $this->crudFlashMessage($request, 'error', sprintf('%s.create.flash.error', $this->crudTransPrefix()));
                }
            }
        }

        $baseTemplateVars = array(
            'request' => $request,
            'object' => $object,
            'form' => $form->createView(),
            'createRoute' => $this->crudCreateRoute(),
            'listRoute' => $this->crudListRoute(),
            'editRoute' => $this->crudEditRoute(),
            'viewRoute' => $this->crudViewRoute(),
            'deleteRoute' => $this->crudDeleteRoute(),
            'listRole' => $this->crudListRole(),
            'createRole' => $this->crudCreateRole(),
            'editRole' => $this->crudEditRole(),
            'viewRole' => $this->crudViewRole(),
            'deleteRole' => $this->crudDeleteRole(),
            'identifier' => $this->crudIdentifier(),
            'transPrefix' => $this->crudTransPrefix(),
            'objectClass' => $this->crudObjectClass(),
        );

        return $this->crudRender(
            $this->crudCreateTemplate(),
            array_replace_recursive($baseTemplateVars, $templateVars)
        );
    }

    /**
     * @param Request           $request
     * @param object|string|int $object
     * @param array             $templateVars
     *
     * @return Response|RedirectResponse
     */
    public function crudEditObject(Request $request, $object, array $templateVars = array())
    {
        if (!is_object($object)) {
            /** @var ObjectRepository $repo */
            $repo = $this->crudRepositoryForClass($this->crudObjectClass());
            $object = $repo->find($object);
        }

        if (null === $object) {
            throw new NotFoundHttpException('There is no object with this id');
        }

        if (!$this->crudEditIsGranted($object)) {
            throw new AccessDeniedException('You need the permission to edit this object!');
        }

        $form = $this->crudForm($this->crudEditFormType($object), $object);

        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($this->crudEditIsSubmitted($form)) {
                if ($form->isValid()) {
                    $this->crudEditPrePersist($object);

                    $em = $this->crudManagerForClass($this->crudObjectClass());
                    $em->persist($object);
                    $em->flush();

                    $this->crudEditPostFlush($object);

                    $this->crudFlashMessage($request, 'success', sprintf('%s.edit.flash.success', $this->crudTransPrefix()));

                    return new RedirectResponse($this->crudEditRedirectUrl($object), 302);
                } else {
                    $this->crudFlashMessage($request, 'error', sprintf('%s.edit.flash.error', $this->crudTransPrefix()));
                }
            }
        }

        $baseTemplateVars = array(
            'request' => $request,
            'object' => $object,
            'form' => $form->createView(),
            'createRoute' => $this->crudCreateRoute(),
            'listRoute' => $this->crudListRoute(),
            'editRoute' => $this->crudEditRoute(),
            'viewRoute' => $this->crudViewRoute(),
            'deleteRoute' => $this->crudDeleteRoute(),
            'listRole' => $this->crudListRole(),
            'createRole' => $this->crudCreateRole(),
            'editRole' => $this->crudEditRole(),
            'viewRole' => $this->crudViewRole(),
            'deleteRole' => $this->crudDeleteRole(),
            'identifier' => $this->crudIdentifier(),
            'transPrefix' => $this->crudTransPrefix(),
            'objectClass' => $this->crudObjectClass(),
        );

        return $this->crudRender(
            $this->crudEditTemplate(),
            array_replace_recursive($baseTemplateVars, $templateVars)
        );
    }

    /**
     * @param Request           $request
     * @param object|string|int $object
     * @param array             $templateVars
     *
     * @return Response|RedirectResponse
     */
    public function crudViewObject(Request $request, $object, array $templateVars = array())
    {
        if (!is_object($object)) {
            /** @var ObjectRepository $repo */
            $repo = $this->crudRepositoryForClass($this->crudObjectClass());
            $object = $repo->find($object);
        }

        if (null === $object) {
            throw new NotFoundHttpException('There is no object with this id');
        }

        if (!$this->crudViewIsGranted($object)) {
            throw new AccessDeniedException('You need the permission to view this object!');
        }

        $baseTemplateVars = array(
            'request' => $request,
            'object' => $object,
            'createRoute' => $this->crudCreateRoute(),
            'listRoute' => $this->crudListRoute(),
            'editRoute' => $this->crudEditRoute(),
            'viewRoute' => $this->crudViewRoute(),
            'deleteRoute' => $this->crudDeleteRoute(),
            'listRole' => $this->crudListRole(),
            'createRole' => $this->crudCreateRole(),
            'editRole' => $this->crudEditRole(),
            'viewRole' => $this->crudViewRole(),
            'deleteRole' => $this->crudDeleteRole(),
            'identifier' => $this->crudIdentifier(),
            'transPrefix' => $this->crudTransPrefix(),
            'objectClass' => $this->crudObjectClass(),
        );

        return $this->crudRender(
            $this->crudViewTemplate(),
            array_replace_recursive($baseTemplateVars, $templateVars)
        );
    }

    /**
     * @param Request           $request
     * @param object|string|int $object
     *
     * @return Response|RedirectResponse
     */
    public function crudDeleteObject(Request $request, $object)
    {
        if (!is_object($object)) {
            /** @var ObjectRepository $repo */
            $repo = $this->crudRepositoryForClass($this->crudObjectClass());
            $object = $repo->find($object);
        }

        if (null === $object) {
            throw new NotFoundHttpException('There is no object with this id');
        }

        if (!$this->crudDeleteIsGranted($object)) {
            throw new AccessDeniedException('You need the permission to delete this object!');
        }

        $this->crudDeletePreRemove($object);

        $em = $this->crudManagerForClass($this->crudObjectClass());
        $em->remove($object);
        $em->flush();

        $this->crudDeletePostFlush($object);

        $this->crudFlashMessage($request, 'success', sprintf('%s.delete.flash.success', $this->crudTransPrefix()));

        return new RedirectResponse($this->crudDeleteRedirectUrl(), 302);
    }

    /**
     * @return int
     */
    protected function crudListPerPage()
    {
        return 10;
    }

    /**
     * @return string
     */
    protected function crudListRoute()
    {
        return strtolower(sprintf($this->crudRoutePattern(), $this->crudName(), 'list'));
    }

    /**
     * @return bool
     */
    protected function crudListIsGranted()
    {
        return $this->crudIsGranted($this->crudListRole());
    }

    /**
     * @return string
     */
    protected function crudListRole()
    {
        return strtoupper(sprintf($this->crudRolePattern(), $this->crudName(), 'list'));
    }

    /**
     * @return FormTypeInterface|null
     */
    protected function crudListFormType()
    {
        return null;
    }

    /**
     * @return array
     */
    protected function crudListFormDataEnrich()
    {
        return array();
    }

    /**
     * @return string
     */
    protected function crudListTemplate()
    {
        return sprintf($this->crudTemplatePattern(), ucfirst($this->crudName()), 'list');
    }

    /**
     * @return string
     */
    protected function crudCreateRoute()
    {
        return strtolower(sprintf($this->crudRoutePattern(), $this->crudName(), 'create'));
    }

    /**
     * @return bool
     */
    protected function crudCreateIsGranted()
    {
        return $this->crudIsGranted($this->crudCreateRole());
    }

    /**
     * @return string
     */
    protected function crudCreateRole()
    {
        return strtoupper(sprintf($this->crudRolePattern(), $this->crudName(), 'create'));
    }

    /**
     * @return object
     */
    protected function crudCreateFactory()
    {
        $objectClass = $this->crudObjectClass();

        return new $objectClass();
    }

    /**
     * @param object $object
     *
     * @return FormTypeInterface
     *
     * @throws \Exception
     */
    protected function crudCreateFormType($object)
    {
        throw new \Exception('You need to implement this method, if you use the createObject method!');
    }

    /**
     * @param FormInterface $form
     *
     * @return bool
     */
    protected function crudCreateIsSubmitted(FormInterface $form)
    {
        $buttonName = $this->crudCreateButtonName();
        if (null !== $buttonName && !$form->get($buttonName)->isClicked()) {
            return false;
        }

        return true;
    }

    /**
     * @return string|null
     */
    protected function crudCreateButtonName()
    {
        return null;
    }

    /**
     * @param object
     *
     * @return string
     */
    protected function crudCreateRedirectUrl($object)
    {
        $identifierMethod = $this->crudIdentifierMethod();

        return $this->crudGenerateRoute($this->crudEditRoute(), array('id' => $object->$identifierMethod()));
    }

    /**
     * @return string
     */
    protected function crudCreateTemplate()
    {
        return sprintf($this->crudTemplatePattern(), ucfirst($this->crudName()), 'create');
    }

    /**
     * @param object $object
     */
    protected function crudCreatePrePersist($object)
    {
    }

    /**
     * @param object $object
     */
    protected function crudCreatePostFlush($object)
    {
    }

    /**
     * @return string
     */
    protected function crudEditRoute()
    {
        return strtolower(sprintf($this->crudRoutePattern(), $this->crudName(), 'edit'));
    }

    /**
     * @param object
     *
     * @return bool
     */
    protected function crudEditIsGranted($object)
    {
        return $this->crudIsGranted($this->crudEditRole(), $object);
    }

    /**
     * @return string
     */
    protected function crudEditRole()
    {
        return strtoupper(sprintf($this->crudRolePattern(), $this->crudName(), 'edit'));
    }

    /**
     * @param object $object
     *
     * @return FormTypeInterface
     *
     * @throws \Exception
     */
    protected function crudEditFormType($object)
    {
        throw new \Exception('You need to implement this method, if you use the editObject method!');
    }

    /**
     * @param FormInterface $form
     *
     * @return bool
     */
    protected function crudEditIsSubmitted(FormInterface $form)
    {
        $buttonName = $this->crudEditButtonName();
        if (null !== $buttonName && !$form->get($buttonName)->isClicked()) {
            return false;
        }

        return true;
    }

    /**
     * @return string|null
     */
    protected function crudEditButtonName()
    {
        return null;
    }

    /**
     * @param object
     *
     * @return string
     */
    protected function crudEditRedirectUrl($object)
    {
        $identifierMethod = $this->crudIdentifierMethod();

        return $this->crudGenerateRoute($this->crudEditRoute(), array('id' => $object->$identifierMethod()));
    }

    /**
     * @return string
     */
    protected function crudEditTemplate()
    {
        return sprintf($this->crudTemplatePattern(), ucfirst($this->crudName()), 'edit');
    }

    /**
     * @param object $object
     */
    protected function crudEditPrePersist($object)
    {
    }

    /**
     * @param object $object
     */
    protected function crudEditPostFlush($object)
    {
    }

    /**
     * @return string
     */
    protected function crudViewRoute()
    {
        return strtolower(sprintf($this->crudRoutePattern(), $this->crudName(), 'view'));
    }

    /**
     * @param object
     *
     * @return bool
     */
    protected function crudViewIsGranted($object)
    {
        return $this->crudIsGranted($this->crudViewRole(), $object);
    }

    /**
     * @return string
     */
    protected function crudViewRole()
    {
        return strtoupper(sprintf($this->crudRolePattern(), $this->crudName(), 'view'));
    }

    /**
     * @return string
     */
    protected function crudViewTemplate()
    {
        return sprintf($this->crudTemplatePattern(), ucfirst($this->crudName()), 'view');
    }

    /**
     * @return string
     */
    protected function crudDeleteRoute()
    {
        return strtolower(sprintf($this->crudRoutePattern(), $this->crudName(), 'delete'));
    }

    /**
     * @param $object
     *
     * @return bool
     */
    protected function crudDeleteIsGranted($object)
    {
        return $this->crudIsGranted($this->crudDeleteRole(), $object);
    }

    /**
     * @return string
     */
    protected function crudDeleteRole()
    {
        return strtoupper(sprintf($this->crudRolePattern(), $this->crudName(), 'delete'));
    }

    /**
     * @return string
     */
    protected function crudDeleteRedirectUrl()
    {
        return $this->crudGenerateRoute($this->crudListRoute());
    }

    /**
     * @param object $object
     */
    protected function crudDeletePreRemove($object)
    {
    }

    /**
     * @param object $object
     */
    protected function crudDeletePostFlush($object)
    {
    }

    /**
     * @return string
     */
    protected function crudRoutePattern()
    {
        return '%s_%s';
    }

    /**
     * @return string
     */
    protected function crudRolePattern()
    {
        return 'role_%s_%s';
    }

    /**
     * @return string
     */
    protected function crudTransPrefix()
    {
        return Helper::camelCaseToUnderscore($this->crudName());
    }

    /**
     * @return string
     *
     * @throws \Exception
     */
    protected function crudTemplatePattern()
    {
        throw new \Exception(sprintf(
            'For actions using a template you need to define the template pattern like this: %s',
            '@SaxulumCrud/%s/%s.html.twig'
        ));
    }

    /**
     * @return string
     */
    abstract protected function crudName();

    /**
     * @return string
     */
    abstract protected function crudObjectClass();

    /**
     * @return AuthorizationCheckerInterface
     *
     * @throws ServiceNotFoundException
     */
    protected function crudAuthorizationChecker()
    {
        throw new ServiceNotFoundException(sprintf(
            'For actions using authorization checker you need: %s',
            'Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface'
        ));
    }

    /**
     * @return SecurityContextInterface
     *
     * @throws ServiceNotFoundException
     */
    protected function crudSecurity()
    {
        throw new ServiceNotFoundException(sprintf(
            'For actions using security you need: %s',
            'Symfony\Component\Security\Core\SecurityContextInterface'
        ));
    }

    /**
     * @return ManagerRegistry
     *
     * @throws ServiceNotFoundException
     */
    protected function crudDoctrine()
    {
        throw new ServiceNotFoundException(sprintf(
            'For actions using doctrine you need: %s',
            'Doctrine\Common\Persistence\ManagerRegistry'
        ));
    }

    /**
     * @return FormFactoryInterface
     *
     * @throws ServiceNotFoundException
     */
    protected function crudFormFactory()
    {
        throw new ServiceNotFoundException(sprintf(
            'For actions using form you need: %s',
            'Symfony\Component\Form\FormFactoryInterface'
        ));
    }

    /**
     * @return PaginatorInterface
     *
     * @throws ServiceNotFoundException
     */
    protected function crudPaginator()
    {
        throw new ServiceNotFoundException(sprintf(
            'For actions using pagination you need: %s',
            'Knp\Component\Pager\PaginatorInterface'
        ));
    }

    /**
     * @return UrlGeneratorInterface
     *
     * @throws ServiceNotFoundException
     */
    protected function crudUrlGenerator()
    {
        throw new ServiceNotFoundException(sprintf(
            'For actions using url generation you need: %s',
            'Symfony\Component\Routing\Generator\UrlGeneratorInterface'
        ));
    }

    /**
     * @return \Twig_Environment
     *
     * @throws ServiceNotFoundException
     */
    protected function crudTwig()
    {
        throw new ServiceNotFoundException(sprintf(
            'For actions using twig you need: %s',
            '\Twig_Environment'
        ));
    }

    /**
     * @param mixed $attributes
     * @param mixed $object
     *
     * @return bool
     *
     * @throws \Exception
     */
    protected function crudIsGranted($attributes, $object = null)
    {
        try {
            return $this->crudAuthorizationChecker()->isGranted($attributes, $object);
        } catch (ServiceNotFoundException $e) {
            return $this->crudSecurity()->isGranted($attributes, $object);
        }
    }

    /**
     * @param string $class
     *
     * @return ObjectManager
     *
     * @throws \Exception
     */
    protected function crudManagerForClass($class)
    {
        $om = $this->crudDoctrine()->getManagerForClass($class);

        if (null === $om) {
            throw new \Exception(sprintf('There is no object manager for class: %s', $class));
        }

        return $om;
    }

    /**
     * @param string $class
     *
     * @return ObjectRepository
     */
    protected function crudRepositoryForClass($class)
    {
        return $this->crudManagerForClass($class)->getRepository($class);
    }

    /**
     * @return string
     *
     * @throws \Exception
     */
    protected function crudIdentifier()
    {
        $em = $this->crudManagerForClass($this->crudObjectClass());
        $meta = $em->getClassMetadata($this->crudObjectClass());

        $identifier = $meta->getIdentifier();

        if (1 !== count($identifier)) {
            throw new \Exception('There are multiple fields define the identifier, which is not supported!');
        }

        return reset($identifier);
    }

    /**
     * @return string
     *
     * @throws \Exception
     */
    protected function crudIdentifierMethod()
    {
        $identifier = $this->crudIdentifier();

        return 'get'.ucfirst($identifier);
    }

    /**
     * @param FormTypeInterface $type
     * @param mixed             $data
     * @param array             $options
     *
     * @return FormInterface
     */
    protected function crudForm(FormTypeInterface $type, $data = null, array $options = array())
    {
        return $this->crudFormFactory()->create($type, $data, $options);
    }

    /**
     * @param object  $qb
     * @param Request $request
     *
     * @return PaginationInterface
     */
    protected function crudPaginate($qb, Request $request)
    {
        return $this->crudPaginator()->paginate(
            $qb,
            $request->query->get('page', 1),
            $request->query->get('perPage', $this->crudListPerPage())
        );
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return string
     */
    protected function crudGenerateRoute($name, array $parameters = array())
    {
        return $this->crudUrlGenerator()->generate($name, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * @param string $view
     * @param array  $parameters
     *
     * @return Response
     */
    protected function crudRender($view, array $parameters = array())
    {
        return new Response($this->crudTwig()->render($view, $parameters));
    }

    /**
     * @param Request $request
     * @param string  $type
     * @param string  $message
     */
    protected function crudFlashMessage(Request $request, $type, $message)
    {
        /** @var Session $session */
        $session = $request->getSession();
        $session->getFlashBag()->add($type, $message);
    }
}
