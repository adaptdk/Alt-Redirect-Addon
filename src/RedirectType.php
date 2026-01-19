<?php

namespace AltDesign\AltRedirect;

enum RedirectType: string
{
	case MOVED_PERMANENTLY = '301';
	case FOUND = '302';
	case TEMPORARY_REDIRECT = '307';
	case PERMANENT_REDIRECT = '308';
}
