from typing import NamedTuple, Optional, List
from config import AppFlags

class Session(NamedTuple):
    """Session data"""
    session_id: int # should be crypto-random
    user_id: Optional[int] # from Scratch
    expiry: int # unix epoch time
    authing: Optional[str] # token-getter code
    nonce: Optional[str] # for logging in

class Nonce(NamedTuple):
    """Object containing nonce"""
    nonce: str # the nonce lol

class PartialApplication(NamedTuple):
    """Partial application data to be fetched in a list"""
    client_id: int
    app_name: Optional[str] # regardless of approval

class Application(NamedTuple):
    """Application data"""
    client_id: int
    client_secret: str
    app_name: Optional[str]
    flags: AppFlags
    redirect_uris: List[str]

class Authing(NamedTuple):
    """Authorization process data"""
    code: str
    user_id: int
    client_id: int
    redirect_uri: str
    scopes: List[str]
    state: Optional[str]
    expiry: Optional[int]

class TokensResponse(NamedTuple):
    """A bundle of tokens data."""
    access_token: str
    access_expiry: int
    refresh_token: str
    refresh_expiry: int
    scopes: List[str]

class Approval(NamedTuple):
    """An approval for an application."""
    refresh_token: str
    client_id: int
    app_name: str
    scopes: List[str]
    expiry: int
    flags: AppFlags

class User(NamedTuple):
    user_id: int
    user_name: str
    data: Optional[str]