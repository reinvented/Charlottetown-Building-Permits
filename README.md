Charlottetown Building Permits
==============================

Copyright 2010 by [Peter Rukavina](http://ruk.ca).

Licensed under [GNU Public License](http://www.fsf.org/licensing/licenses/gpl.txt).

The [City of Charlottetown](http://www.charlottetown.pe.ca), Prince Edward Island has historically released building permit, subdivision and rezoning approval data only in [PDF format](http://city.charlottetown.pe.ca/buildingpermitapproval.php), which affords citizens with limited opportunties to use this data to build value-added applications.

This project is an attempt to provide the City with the data formats, tools and sample applications necessary to migrate to an XML-based method for releasing this data to the public.

What's Here
-----------

* Weekly_approvals_webpage_11_Mar_2011.pdf - an example of the original PDF file the City of Charlottetown posts of its website
* charlottetown-permits-2011-03-11.xml - A hand-coded XML version of the data in that PDF file.
* charlottetown-permits.dtd - An XML DTD for that XML file.
* charlottetown-permits.xsd - An XML Schema for that XML file.
* get-address-from-pid.php - A PHP script to retrieve handy civic address information for a given PID.
