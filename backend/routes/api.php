<?php
declare(strict_types=1);

namespace CampusSOS\Routes;

use CampusSOS\Controllers\HealthController;
use CampusSOS\Controllers\AuthController;
use CampusSOS\Controllers\IncidentController;
use CampusSOS\Controllers\SosController;
use CampusSOS\Controllers\ResponderController;
use CampusSOS\Controllers\AdminController;
use CampusSOS\Controllers\LocationController;
use CampusSOS\Controllers\NotificationController;
use CampusSOS\Controllers\ContactController;
use CampusSOS\Controllers\AttachmentController;

function registerRoutes(Router $router): void
{
    // Health Check Endpoints
    $router->get('/api/health', [HealthController::class, 'apiHealth']);
    $router->get('/api/health/db', [HealthController::class, 'dbHealth']);

    // Campus Locations
    $router->get('/api/locations', [LocationController::class, 'index']);

    // Auth Endpoints
    $router->get('/api/auth/csrf', [AuthController::class, 'csrf']);
    $router->get('/api/auth/me', [AuthController::class, 'me']);
    $router->post('/api/auth/login', [AuthController::class, 'login']);
    $router->post('/api/auth/register', [AuthController::class, 'register']);
    $router->post('/api/auth/logout', [AuthController::class, 'logout']);

    // Incident Endpoints
    $router->get('/api/incidents', [IncidentController::class, 'index']);
    $router->post('/api/incidents', [IncidentController::class, 'create']);
    $router->get('/api/incidents/{id}', [IncidentController::class, 'show']);
    $router->put('/api/incidents/{id}/status', [IncidentController::class, 'updateStatus']);
    $router->post('/api/incidents/{id}/assign', [IncidentController::class, 'assign']);
    $router->post('/api/incidents/{id}/acknowledge', [IncidentController::class, 'acknowledgeAssignment']);

    // Emergency SOS Endpoints (Deterministic Dispatch)
    $router->post('/api/sos', [SosController::class, 'trigger']);
    $router->post('/api/sos/trigger', [SosController::class, 'trigger']);
    $router->get('/api/sos/active', [SosController::class, 'active']);
    $router->get('/api/sos/{id}', [SosController::class, 'show']);
    $router->post('/api/sos/{id}/acknowledge', [SosController::class, 'acknowledge']);
    $router->post('/api/sos/{id}/assign', [SosController::class, 'assign']);
    $router->post('/api/sos/{id}/resolve', [SosController::class, 'resolve']);
    $router->get('/api/sos/contacts', [SosController::class, 'contacts']);

    // Responders & Directory
    $router->get('/api/responders', [ResponderController::class, 'index']);
    $router->get('/api/responders/category/{category}', [ResponderController::class, 'byCategory']);
    $router->get('/api/responders/my-assignments', [ResponderController::class, 'myAssignments']);
    $router->put('/api/responders/status', [ResponderController::class, 'updateStatus']);

    // Notifications (Polling or on-demand fetch)
    $router->get('/api/notifications', [NotificationController::class, 'index']);
    $router->put('/api/notifications/{id}/read', [NotificationController::class, 'markRead']);
    $router->put('/api/notifications/read-all', [NotificationController::class, 'markAllRead']);

    // Admin & Ops Metrics (Restricted to role='admin')
    $router->get('/api/admin/metrics', [AdminController::class, 'dashboardMetrics']);
    $router->get('/api/admin/audit-logs', [AdminController::class, 'auditLogs']);
    $router->get('/api/admin/matches', [AdminController::class, 'matches']);
    $router->get('/api/admin/matches/{id}', [AdminController::class, 'showMatch']);
    $router->post('/api/admin/matches/{id}/merge', [AdminController::class, 'mergeMatch']);
    $router->post('/api/admin/matches/{id}/dismiss', [AdminController::class, 'dismissMatch']);
    $router->post('/api/admin/matches/{id}/review', [AdminController::class, 'reviewMatch']);

    // Public & Admin Department Contacts
    $router->get('/api/contacts', [ContactController::class, 'index']);
    $router->get('/api/contacts/{id}', [ContactController::class, 'show']);
    $router->put('/api/contacts/{id}', [ContactController::class, 'update']);

    // Visual Evidence & Attachments
    $router->get('/api/attachments/{id}', [AttachmentController::class, 'show']);
    $router->get('/api/attachments/{id}/file', [AttachmentController::class, 'serveFile']);
}
