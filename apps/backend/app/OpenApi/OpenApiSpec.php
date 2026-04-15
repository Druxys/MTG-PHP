<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'MTG Backend API',
    description: 'OpenAPI documentation for the MTG backend.'
)]
#[OA\Server(url: '/', description: 'Application root URL')]
#[OA\Tag(name: 'Auth', description: 'Authentication endpoints')]
#[OA\Tag(name: 'Cards', description: 'Card listing and creation endpoints')]
#[OA\Schema(
    schema: 'AuthUser',
    required: ['id', 'name', 'email'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Paul Turpin'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'paul@example.com'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'AuthTokenResponse',
    required: ['message', 'token', 'expiresAt'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Authenticated successfully'),
        new OA\Property(property: 'token', type: 'string', example: 'f2dce95f...'),
        new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Card',
    required: ['id', 'name', 'rarity', 'type', 'text', 'colors', 'hasImage'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: '42'),
        new OA\Property(property: 'name', type: 'string', example: 'Lightning Bolt'),
        new OA\Property(property: 'rarity', type: 'string', enum: ['common', 'uncommon', 'rare', 'mythic']),
        new OA\Property(property: 'type', type: 'string', example: 'Instant'),
        new OA\Property(property: 'text', type: 'string', example: 'Deal 3 damage to any target.'),
        new OA\Property(property: 'manaCost', type: 'string', nullable: true, example: '{R}'),
        new OA\Property(property: 'convertedManaCost', type: 'number', format: 'float', example: 1),
        new OA\Property(property: 'colors', type: 'array', items: new OA\Items(type: 'string', enum: ['W', 'U', 'B', 'R', 'G'])),
        new OA\Property(property: 'scryfallId', type: 'string', nullable: true),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'hasImage', type: 'boolean', example: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Pagination',
    required: ['currentPage', 'totalPages', 'totalCards', 'hasNext', 'hasPrev'],
    properties: [
        new OA\Property(property: 'currentPage', type: 'integer', example: 1),
        new OA\Property(property: 'totalPages', type: 'integer', example: 10),
        new OA\Property(property: 'totalCards', type: 'integer', example: 100),
        new OA\Property(property: 'hasNext', type: 'boolean', example: true),
        new OA\Property(property: 'hasPrev', type: 'boolean', example: false),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ApiError',
    required: ['error'],
    properties: [
        new OA\Property(property: 'error', type: 'string', example: 'Validation failed'),
        new OA\Property(property: 'details', type: 'array', items: new OA\Items(type: 'string')),
    ],
    type: 'object'
)]
class OpenApiSpec {}
