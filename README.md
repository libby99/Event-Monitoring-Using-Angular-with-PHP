# Event-Monitoring-Using-Angular-with-PHP

Angular CLI application is used to execute DLL API query operations via PHP API services, which send HTTP requests to server
and get the responses. It will parse the events from the response data to generate the information displayed in the browser.

To run the program, I assume you have the following prerequisites.

Prerequisites

(1) Web server such as Wampserver installed on your development machine.
(2) PHP required by the back-end project installed on your system.
(3) Node.js 8.9+ and NPM installed in your system. This is only required by your Angular project.
(4) Angular CLI installed on your development machine. Angular CLI I am using is version 8.3.8.

Running the project

After building the project with ng serve command of Angular CLI, You can access the frontend application by your browser
using http://localhost:4200 address. The localhost:80 domain will be set for the RESTful PHP backend.

Code structure

(1)ictAssignment - Angular CLI application
  app - app.component.ts - Angular component that will call the service methods and will submit a query to the PHP backend.
        api.service.ts - Angular service that encapsulates all the code needed for interfacing with the RESTful PHP backend.
        app.component.html - display the events. 
        app.component.css - css style file

  assets - encrypt.js - encryption and decryption operation.
           functions.js - provide the functions required by the component.

(2)ictAssignmentAPI - PHP API code 

 1) index.php - Send API request to server and get the response data.
 2) resource.php - execute the login authentication, getting the POST data and returning JSON data in the code.

Note

CORS have been enabled so two domains localhost:80 and localhost:4200 can be used for respectively serving PHP and Angular 
and being able to send requests from Angular, PHP to sandbox device without getting blocked by the Same Origin Policy rule
in web browsers.
