"""
Database Schema & Migration Validation Inspector
Script: db_schema_inspector.py

Automates verification of database schema consistency, table structures,
and column definitions across development, staging, and production environments.
"""

import sys
import os
import mysql.connector

def verify_database_schema(host, user, password, database):
    print(f"[*] Connecting to database: {database} on {host}...")
    try:
        conn = mysql.connector.connect(
            host=host,
            user=user,
            password=password,
            database=database
        )
        cursor = conn.cursor()

        # 1. Enumerate tables
        cursor.execute("SHOW TABLES")
        tables = [table[0] for table in cursor.fetchall()]
        print(f"[+] Total tables discovered: {len(tables)}")
        
        required_tables = [
            'employees', 
            'asset_history', 
            'payroll_records', 
            'attendance_logs', 
            'salary_audit_log'
        ]

        print("\n--- Required Table Presence Verification ---")
        for req in required_tables:
            status = "FOUND" if req in tables else "MISSING"
            print(f"  [{status}] {req}")

        # 2. Inspect key table columns
        target_table = 'payroll_records'
        if target_table in tables:
            print(f"\n--- Column Inspection: {target_table} ---")
            cursor.execute(f"DESCRIBE {target_table}")
            columns = cursor.fetchall()
            for col in columns:
                col_name, col_type, is_null, key, default, extra = col
                print(f"  - {col_name:<25} {col_type:<15} Nullable: {is_null:<5} Key: {key}")

        cursor.close()
        conn.close()
        print("\n[+] Database schema inspection completed successfully.")

    except mysql.connector.Error as err:
        print(f"[!] Database connection or query error: {err}")
        sys.exit(1)

if __name__ == "__main__":
    db_host = os.getenv("DB_HOST", "localhost")
    db_user = os.getenv("DB_USER", "db_admin")
    db_pass = os.getenv("DB_PASS", "secret_password")
    db_name = os.getenv("DB_NAME", "enterprise_hrms")

    verify_database_schema(db_host, db_user, db_pass, db_name)
