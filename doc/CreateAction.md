# Create action

This methods implements a simple, but usefull object create action.

## Api

```{.php}
/**
 * @param  Request                   $request
 * @param  array                     $templateVars
 * @return Response|RedirectResponse
 */
public function crudCreateObject(Request $request, array $templateVars = array())
```

## Template Vars

 * `request`: contains the Request object
 * `object`: contains the object
 * `form`: contains a form view
 * `listRoute`: contains the name of the list route
 * `createRoute`: contains the name of the create route
 * `editRoute`: contains the name of the edit route
 * `viewRoute`: contains the name of the view route
 * `deleteRoute`: contains the name of the delete route
 * `listRole`: contains the name of the delete role
 * `createRole`: contains the name of the delete role
 * `editRole`: contains the name of the delete role
 * `viewRole`: contains the name of the delete role
 * `deleteRole`: contains the name of the delete role
 * `identifier`: contains property name of the id of the object (`id` in most cases)
 * `transPrefix`: contains the translation prefix (`Controller::crudName()`)

## Overwrites

### Mandatory

#### Create form type

This method defines a form type.

```{.php}
/**
 * @param  object $object
 * @return FormTypeInterface
 */
protected function crudCreateFormType($object)
```

### Facultative

#### Create route

This method defines the create route name

```{.php}
/**
 * @return string
 */
protected function crudCreateRoute()
```

#### Create is granted

This methods return if its allowed to call this object create action.

```{.php}
/**
 * @return bool
 */
protected function crudCreateIsGranted()
```

#### Create role

This method defines the create role (for security check).

```{.php}
/**
 * @return string
 */
protected function crudCreateRole()
```

#### Create factory

This method creates a new object.

```{.php}
/**
 * @return object
 */
protected function crudCreateFactory()
```

#### Create is submitted

This method checks if the form is submitted, this allows form reloads with js for example.

```{.php}
/**
 * @param FormInterface $form
 * @return bool
 */
protected function crudCreateIsSubmitted(FormInterface $form)
```

#### Create button name

This method returns the button name used by: [is submitted][1]

```{.php}
/**
 * @return string|null
 */
protected function crudCreateButtonName()
```

#### Create Redirect url

This method defines the redirect url after create object

```{.php}
/**
 * @param  object $object
 * @return string
 */
protected function crudCreateRedirectUrl($object)
```

#### Create Template

This method defines the template path.

```{.php}
/**
 * @return string
 */
protected function crudCreateTemplate()
```

## Hooks

#### Create pre persist

This method allows to manipulate the object before persist

```{.php}
/**
 * @param  object $object
 * @return void
 */
protected function crudCreatePrePersist($object)
```

#### Create post flush

This method allows to manipulate the object after flush

```{.php}
/**
 * @param  object $object
 * @return void
 */
protected function crudCreatePostFlush($object)
```

[1]: #edit-is-submitted