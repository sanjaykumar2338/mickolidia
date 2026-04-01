<?php

namespace App\Support\CTrader;

class PayloadType
{
    public const ERROR_RES = 50;
    public const HEARTBEAT_EVENT = 51;

    public const APPLICATION_AUTH_REQ = 2100;
    public const APPLICATION_AUTH_RES = 2101;
    public const ACCOUNT_AUTH_REQ = 2102;
    public const ACCOUNT_AUTH_RES = 2103;
    public const TRADER_REQ = 2121;
    public const TRADER_RES = 2122;
    public const RECONCILE_REQ = 2124;
    public const RECONCILE_RES = 2125;
    public const DEAL_LIST_REQ = 2133;
    public const DEAL_LIST_RES = 2134;
    public const OA_ERROR_RES = 2142;
    public const GET_ACCOUNTS_BY_ACCESS_TOKEN_REQ = 2149;
    public const GET_ACCOUNTS_BY_ACCESS_TOKEN_RES = 2150;
    public const ACCOUNTS_TOKEN_INVALIDATED_EVENT = 2147;
    public const CLIENT_DISCONNECT_EVENT = 2148;
    public const ORDER_LIST_REQ = 2175;
    public const ORDER_LIST_RES = 2176;
    public const GET_POSITION_UNREALIZED_PNL_REQ = 2187;
    public const GET_POSITION_UNREALIZED_PNL_RES = 2188;
}
