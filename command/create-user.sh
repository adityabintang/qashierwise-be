#!/bin/bash

# Interactive User Creation Script with Role Assignment
# This script creates a new user and assigns a role from available roles in database

# Colors for better UX
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color
BOLD='\033[1m'

# Function to print colored output
print_header() {
    echo -e "\n${CYAN}${BOLD}========================================${NC}"
    echo -e "${CYAN}${BOLD}   $1${NC}"
    echo -e "${CYAN}${BOLD}========================================${NC}\n"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

# Function to validate email format
validate_email() {
    local email=$1
    if [[ $email =~ ^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$ ]]; then
        return 0
    else
        return 1
    fi
}

# Function to check if email exists
check_email_exists() {
    local email=$1
    local result=$(php artisan tinker --execute="echo \App\Models\User::where('email', '$email')->exists() ? 'true' : 'false';" 2>/dev/null | tail -n 1)
    if [ "$result" = "true" ]; then
        return 0
    else
        return 1
    fi
}

# Function to get available roles from database
get_roles() {
    php artisan tinker --execute="
        \$roles = \Spatie\Permission\Models\Role::where('guard_name', 'sanctum')
            ->whereNull('user_id')
            ->orderBy('name')
            ->get(['id', 'name']);
        foreach (\$roles as \$role) {
            echo \$role->id . '|' . \$role->name . PHP_EOL;
        }
    " 2>/dev/null | grep -v "^$"
}

# Function to display role with formatting
display_role() {
    local role_name=$1
    local is_selected=$2
    
    local prefix="  "
    if [ "$is_selected" = "true" ]; then
        prefix="${BOLD}▶ "
    fi
    
    if [ "$role_name" = "author" ]; then
        echo -e "${prefix}${MAGENTA}$role_name${NC} ${YELLOW}(Blog Content Manager)${NC}"
    elif [ "$role_name" = "Owner" ] || [ "$role_name" = "Manager" ]; then
        echo -e "${prefix}${GREEN}$role_name${NC} ${CYAN}(Management)${NC}"
    else
        echo -e "${prefix}${BLUE}$role_name${NC}"
    fi
}

# Function to display interactive menu with arrow keys
select_role_menu() {
    local selected=0
    local total_roles=${#ROLES_ARRAY[@]}
    
    # Save cursor position
    tput sc
    
    while true; do
        # Restore cursor position and clear from cursor down
        tput rc
        tput ed
        
        # Display all roles
        for ((i=0; i<$total_roles; i++)); do
            IFS='|' read -r role_id role_name <<< "${ROLES_ARRAY[$i]}"
            
            if [ $i -eq $selected ]; then
                display_role "$role_name" "true"
            else
                display_role "$role_name" "false"
            fi
        done
        
        # Read arrow keys
        read -rsn1 key
        
        if [ "$key" = $'\x1b' ]; then
            read -rsn2 key
            case "$key" in
                '[A')  # Up arrow
                    ((selected--))
                    if [ $selected -lt 0 ]; then
                        selected=$((total_roles-1))
                    fi
                    ;;
                '[B')  # Down arrow
                    ((selected++))
                    if [ $selected -ge $total_roles ]; then
                        selected=0
                    fi
                    ;;
            esac
        elif [ "$key" = "" ]; then
            # Enter key pressed
            echo ""
            return $selected
        fi
    done
}

# Function to create user
create_user() {
    local name=$1
    local email=$2
    local password=$3
    local role_id=$4
    local is_master=$5
    
    php artisan tinker --execute="
        try {
            \$user = \App\Models\User::create([
                'name' => '$name',
                'email' => '$email',
                'password' => \Illuminate\Support\Facades\Hash::make('$password'),
                'is_master_admin' => $is_master,
                'email_verified_at' => now(),
            ]);
            
            \$role = \Spatie\Permission\Models\Role::find($role_id);
            if (\$role) {
                \$user->assignRole(\$role->name);
                echo 'SUCCESS|' . \$user->id . '|' . \$role->name;
            } else {
                echo 'ERROR|Role not found';
            }
        } catch (\Exception \$e) {
            echo 'ERROR|' . \$e->getMessage();
        }
    " 2>/dev/null | tail -n 1
}

# Clear screen
clear

# Print header
print_header "QashierWise User Creation Tool"

print_info "This tool will help you create a new user with a specific role."
echo ""

# Step 1: Select role FIRST
print_header "Step 1: Role Selection"

print_info "Fetching available roles from database..."
echo ""

# Get roles from database
roles_data=$(get_roles)

if [ -z "$roles_data" ]; then
    print_error "No roles found in database!"
    print_warning "Please run: php artisan db:seed --class=RoleSeeder"
    exit 1
fi

# Build roles array
declare -a ROLES_ARRAY
while IFS='|' read -r role_id role_name; do
    ROLES_ARRAY+=("$role_id|$role_name")
done <<< "$roles_data"

# Display interactive menu
echo -e "${BOLD}Available Roles:${NC}"
echo -e "${CYAN}Use ↑/↓ arrow keys to navigate, Enter to select${NC}"
echo ""

# Get user selection
select_role_menu
role_choice=$?

# Get selected role details
IFS='|' read -r selected_role_id selected_role_name <<< "${ROLES_ARRAY[$role_choice]}"

print_success "Selected role: ${MAGENTA}$selected_role_name${NC}"
echo ""

# Step 2: Get user details
print_header "Step 2: User Information"

# Get name
while true; do
    echo -ne "${BOLD}Enter full name:${NC} "
    read -r user_name
    
    if [ -z "$user_name" ]; then
        print_error "Name cannot be empty!"
        continue
    fi
    
    if [ ${#user_name} -lt 3 ]; then
        print_error "Name must be at least 3 characters!"
        continue
    fi
    
    break
done

# Get email
while true; do
    echo -ne "${BOLD}Enter email address:${NC} "
    read -r user_email
    
    if [ -z "$user_email" ]; then
        print_error "Email cannot be empty!"
        continue
    fi
    
    if ! validate_email "$user_email"; then
        print_error "Invalid email format!"
        continue
    fi
    
    if check_email_exists "$user_email"; then
        print_error "Email already exists in database!"
        continue
    fi
    
    break
done

# Get password
while true; do
    echo -ne "${BOLD}Enter password (min 8 characters):${NC} "
    read -s user_password
    echo ""
    
    if [ -z "$user_password" ]; then
        print_error "Password cannot be empty!"
        continue
    fi
    
    if [ ${#user_password} -lt 8 ]; then
        print_error "Password must be at least 8 characters!"
        continue
    fi
    
    echo -ne "${BOLD}Confirm password:${NC} "
    read -s user_password_confirm
    echo ""
    
    if [ "$user_password" != "$user_password_confirm" ]; then
        print_error "Passwords do not match!"
        continue
    fi
    
    break
done

# Step 3: Master Admin option (skip for author role)
if [ "$selected_role_name" = "author" ]; then
    # Author role cannot be master admin (blocked from POS login)
    is_master_admin="false"
    echo ""
    print_info "Role 'author' is automatically set as non-master admin (cannot access POS dashboard)"
    echo ""
else
    print_header "Step 3: Account Type"
    
    echo -e "${BOLD}Is this user a Master Admin (Merchant Owner)?${NC}"
    echo ""
    echo -e "  ${GREEN}[Y]${NC} Yes - Can create stores and manage sub-accounts"
    echo -e "  ${BLUE}[N]${NC} No  - Regular user with assigned role only"
    echo ""
    
    while true; do
        echo -ne "${BOLD}Master Admin? (Y/N):${NC} "
        read -r is_master_choice
        
        is_master_choice=$(echo "$is_master_choice" | tr '[:lower:]' '[:upper:]')
        
        if [ "$is_master_choice" = "Y" ] || [ "$is_master_choice" = "YES" ]; then
            is_master_admin="true"
            break
        elif [ "$is_master_choice" = "N" ] || [ "$is_master_choice" = "NO" ]; then
            is_master_admin="false"
            break
        else
            print_error "Please enter Y or N"
        fi
    done
fi

# Step 4: Confirmation
print_header "Step 4: Confirmation"

echo -e "${BOLD}Please review the information:${NC}"
echo ""
echo -e "  ${CYAN}Role:${NC}         ${MAGENTA}$selected_role_name${NC}"
echo -e "  ${CYAN}Name:${NC}         $user_name"
echo -e "  ${CYAN}Email:${NC}        $user_email"
echo -e "  ${CYAN}Password:${NC}     ${GREEN}********${NC} (hidden)"
echo -e "  ${CYAN}Master Admin:${NC} $([ "$is_master_admin" = "true" ] && echo "${GREEN}Yes${NC}" || echo "${BLUE}No${NC}")"
echo ""

while true; do
    echo -ne "${BOLD}Create this user? (Y/N):${NC} "
    read -r confirm
    
    confirm=$(echo "$confirm" | tr '[:lower:]' '[:upper:]')
    
    if [ "$confirm" = "Y" ] || [ "$confirm" = "YES" ]; then
        break
    elif [ "$confirm" = "N" ] || [ "$confirm" = "NO" ]; then
        print_warning "User creation cancelled."
        exit 0
    else
        print_error "Please enter Y or N"
    fi
done

# Step 5: Create user
print_header "Step 5: Creating User"

print_info "Creating user in database..."

result=$(create_user "$user_name" "$user_email" "$user_password" "$selected_role_id" "$is_master_admin")

IFS='|' read -r status message extra <<< "$result"

if [ "$status" = "SUCCESS" ]; then
    echo ""
    print_success "User created successfully!"
    echo ""
    echo -e "${GREEN}${BOLD}User Details:${NC}"
    echo -e "  ${CYAN}User ID:${NC}      $message"
    echo -e "  ${CYAN}Name:${NC}         $user_name"
    echo -e "  ${CYAN}Email:${NC}        $user_email"
    echo -e "  ${CYAN}Role:${NC}         $extra"
    echo -e "  ${CYAN}Master Admin:${NC} $([ "$is_master_admin" = "true" ] && echo "Yes" || echo "No")"
    echo ""
    
    # Special message for author role
    if [ "$selected_role_name" = "author" ]; then
        print_info "This user can access Filament admin panel at: ${BOLD}http://localhost:8000/admin/login${NC}"
        print_warning "This user CANNOT access POS dashboard (blocked by system)"
    fi
    
    # Special message for master admin
    if [ "$is_master_admin" = "true" ]; then
        print_info "This user can create stores and manage POS sub-accounts"
    fi
    
    echo ""
    print_success "User is ready to login!"
    echo ""
else
    echo ""
    print_error "Failed to create user!"
    print_error "Error: $message"
    echo ""
    exit 1
fi

print_header "Done"
