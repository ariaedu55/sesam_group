<?php

declare(strict_types=1);

namespace Sesam\Core\Auth;

final class Permission
{
    public const USERS_VIEW = 'users.view';
    public const USERS_CREATE = 'users.create';
    public const USERS_UPDATE = 'users.update';
    public const USERS_DEACTIVATE = 'users.deactivate';
    public const USERS_SET_PASSWORD = 'users.set_password';
    public const USERS_ASSIGN_ROLES = 'users.assign_roles';
    public const TENANTS_VIEW = 'tenants.view';
    public const TENANTS_CREATE = 'tenants.create';
    public const TENANTS_UPDATE = 'tenants.update';
    public const SUBSCRIPTIONS_VIEW = 'subscriptions.view';
    public const SUBSCRIPTIONS_MANAGE = 'subscriptions.manage';
    public const SETTINGS_VIEW = 'settings.view';
    public const SETTINGS_UPDATE = 'settings.update';
    public const CLIENTS_VIEW = 'clients.view';
    public const CLIENTS_CREATE = 'clients.create';
    public const CLIENTS_UPDATE = 'clients.update';
    public const CLIENTS_ARCHIVE = 'clients.archive';
    public const CLIENTS_EXPORT = 'clients.export';
    public const CLIENTS_NOTES = 'clients.notes';
    public const CLIENTS_TAGS = 'clients.tags';
    public const PAYMENTS_VIEW = 'payments.view';
    public const PAYMENTS_CREATE = 'payments.create';
    public const NOTIFICATIONS_SEND = 'notifications.send';
    public const NOTIFICATIONS_HISTORY = 'notifications.history';
    public const RESERVATION_VIEW = 'reservation.view';
    public const RESERVATION_SERVICES = 'reservation.services';
    public const RESERVATION_STAFF = 'reservation.staff';
    public const RESERVATION_APPOINTMENTS = 'reservation.appointments';
    public const RESERVATION_SETTINGS = 'reservation.settings';
    public const BOUTIQUE_VIEW = 'boutique.view';
    public const BOUTIQUE_PRODUCTS = 'boutique.products';
    public const BOUTIQUE_ORDERS = 'boutique.orders';
    public const BOUTIQUE_SETTINGS = 'boutique.settings';
    public const LOYER_VIEW = 'loyer.view';
    public const LOYER_PROPERTIES = 'loyer.properties';
    public const LOYER_LEASES = 'loyer.leases';
    public const LOYER_DUES = 'loyer.dues';
    public const LOYER_SETTINGS = 'loyer.settings';
    public const SUBSCRIPTION_PAY = 'subscription.pay';
    public const AGENCY_VIEW = 'agency.view';
    public const AGENCY_PROSPECTS = 'agency.prospects';
    public const AGENCY_QUOTES = 'agency.quotes';
    public const AGENCY_PROJECTS = 'agency.projects';
    public const AGENCY_INVOICES = 'agency.invoices';
    public const STATS_VIEW = 'stats.view';
    public const REVENUE_VIEW = 'revenue.view';

    /** @var list<string> */
    public const ALL = [
        self::USERS_VIEW,
        self::USERS_CREATE,
        self::USERS_UPDATE,
        self::USERS_DEACTIVATE,
        self::USERS_SET_PASSWORD,
        self::USERS_ASSIGN_ROLES,
        self::TENANTS_VIEW,
        self::TENANTS_CREATE,
        self::TENANTS_UPDATE,
        self::SUBSCRIPTIONS_VIEW,
        self::SUBSCRIPTIONS_MANAGE,
        self::SETTINGS_VIEW,
        self::SETTINGS_UPDATE,
        self::CLIENTS_VIEW,
        self::CLIENTS_CREATE,
        self::CLIENTS_UPDATE,
        self::CLIENTS_ARCHIVE,
        self::CLIENTS_EXPORT,
        self::CLIENTS_NOTES,
        self::CLIENTS_TAGS,
        self::PAYMENTS_VIEW,
        self::PAYMENTS_CREATE,
        self::NOTIFICATIONS_SEND,
        self::NOTIFICATIONS_HISTORY,
        self::RESERVATION_VIEW,
        self::RESERVATION_SERVICES,
        self::RESERVATION_STAFF,
        self::RESERVATION_APPOINTMENTS,
        self::RESERVATION_SETTINGS,
        self::BOUTIQUE_VIEW,
        self::BOUTIQUE_PRODUCTS,
        self::BOUTIQUE_ORDERS,
        self::BOUTIQUE_SETTINGS,
        self::LOYER_VIEW,
        self::LOYER_PROPERTIES,
        self::LOYER_LEASES,
        self::LOYER_DUES,
        self::LOYER_SETTINGS,
        self::SUBSCRIPTION_PAY,
        self::AGENCY_VIEW,
        self::AGENCY_PROSPECTS,
        self::AGENCY_QUOTES,
        self::AGENCY_PROJECTS,
        self::AGENCY_INVOICES,
        self::STATS_VIEW,
        self::REVENUE_VIEW,
    ];
}
