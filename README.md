
# Swift Auth

Swift Auth is an efficient system designed for user management, providing an intuitive and scalable solution to manage access and permissions.


## Environment Variables

To run this project, you will need to add the following environment variables to your .env file

`SWIFT_AUTH_FRONTEND`

`SWIFT_AUTH_SUCCESS_URL`


the environment variable can only have one of the following values: **typescript | blade** | **javascript**.

**example:**

`SWIFT_AUTH_FRONTEND` = typescript


with `SWIFT_AUTH_SUCCESS_URL` indicates which path to go to once logged in.

**example:**

`SWIFT_AUTH_SUCCESS_URL`= '/dashboard'

## Installation

- Create project
- cd my-project

**Composer**
```bash
  composer require teleurban/switft-auth
``` 
if it displays an error uses: 

**Composer beta**
```bash
  composer require teleurban/switft-auth:dev-main
``` 

**Swift auth Installation**
```bash
  php artisan swift-auth:install
``` 
**Once the command is executed, it asks if you want to publish different files**


**Run migrations**
```bash
  php artisan migrate
``` 


## Middleware

The Middleware must be added to the routes that require it as follows: 

```bash
  middleware('SwiftAuthMiddleware')
``` 



