#!/bin/bash
set -euo pipefail

# Logger Function
log() {
    local message="$1"
    local type="${2:-info}"
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    local color endcolor="\033[0m"

    case "$type" in
        info) color="\033[38;5;79m" ;;
        success) color="\033[1;32m" ;;
        error) color="\033[1;31m" ;;
        *) color="\033[1;34m" ;;
    esac

    echo -e "${color}${timestamp} - ${message}${endcolor}"
}

# Error handler function
handle_error() {
    local exit_code="$1"
    local error_message="$2"
    log "Error: $error_message (Exit Code: $exit_code)" "error"
    exit "$exit_code"
}

# Function to check for command availability
command_exists() {
    command -v "$1" &> /dev/null
}

check_root() {
    if [ "$(id -u)" -ne 0 ]; then
        handle_error 1 "This script must be run as root (try: sudo $0)."
    fi
}

check_os() {
    if [ ! -f /etc/debian_version ]; then
        handle_error 1 "This script is only supported on Debian-based systems."
    fi
}

# Function to install the script pre-requisites
install_pre_reqs() {
    log "Installing pre-requisites" "info"

    export DEBIAN_FRONTEND=noninteractive

    if ! apt-get update -y; then
        handle_error "$?" "Failed to run 'apt-get update'"
    fi

    if ! apt-get install -y apt-transport-https ca-certificates curl gnupg; then
        handle_error "$?" "Failed to install packages"
    fi

    if ! mkdir -p /usr/share/keyrings; then
        handle_error "$?" "Failed to create /usr/share/keyrings"
    fi

    rm -f /usr/share/keyrings/nodesource.gpg
    rm -f /etc/apt/sources.list.d/nodesource.list

    if ! curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
            | gpg --dearmor -o /usr/share/keyrings/nodesource.gpg; then
        handle_error "$?" "Failed to download and import the NodeSource signing key"
    fi
}

# Function to configure the repo
configure_repo() {
    local node_version="$1"
    local arch
    arch=$(dpkg --print-architecture)

    case "$arch" in
        amd64|arm64|armhf) ;;
        *) handle_error 1 "Unsupported architecture: $arch. Only amd64, arm64, and armhf are supported." ;;
    esac

    echo "deb [arch=$arch signed-by=/usr/share/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$node_version nodistro main" \
        | tee /etc/apt/sources.list.d/nodesource.list > /dev/null

    # N|solid pin
    {
        echo "Package: nsolid"
        echo "Pin: origin deb.nodesource.com"
        echo "Pin-Priority: 600"
    } | tee /etc/apt/preferences.d/nsolid > /dev/null

    # Node.js pin
    {
        echo "Package: nodejs"
        echo "Pin: origin deb.nodesource.com"
        echo "Pin-Priority: 600"
    } | tee /etc/apt/preferences.d/nodejs > /dev/null

    if ! apt-get update -y; then
        handle_error "$?" "Failed to run 'apt-get update'"
    else
        log "Repository configured successfully. To install Node.js, run: apt-get install nodejs -y" "success"
    fi
}

# Node.js version - override with: ./nodesource_setup.sh 22.x
NODE_VERSION="${1:-20.x}"

check_root
check_os

install_pre_reqs || handle_error $? "Failed installing pre-requisites"
configure_repo "$NODE_VERSION" || handle_error $? "Failed configuring repository"
