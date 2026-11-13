<?php
/**
 * ReportModel
 *
 * Encapsulates every database query used by the Reports page.
 * All SQL is moved here verbatim from the original pages/reports.php —
 * no query logic has been changed.
 *
 * Depends on: includes/db.php (db_query helper must already be loaded)
 */
class ReportModel
{
    /**
     * Contract counts grouped by Status.
     */
    public function getByStatus(): array
    {
        return db_query(
            "SELECT Status, COUNT(*) AS cnt
             FROM Contract
             GROUP BY Status
             ORDER BY cnt DESC"
        )->fetchAll();
    }

    /**
     * Contract counts and total values grouped by ContractType.
     */
    public function getByType(): array
    {
        return db_query(
            "SELECT ct.TypeName, COUNT(c.ContractID) AS cnt,
                    SUM(c.ContractValue) AS total_value
             FROM Contract c
             JOIN ContractType ct ON ct.ContractTypeID = c.ContractTypeID
             GROUP BY ct.TypeName
             ORDER BY cnt DESC"
        )->fetchAll();
    }

    /**
     * Contract counts grouped by Department.
     */
    public function getByDepartment(): array
    {
        return db_query(
            "SELECT d.DepartmentName, COUNT(c.ContractID) AS cnt
             FROM Contract c
             JOIN Department d ON d.DepartmentID = c.DepartmentID
             GROUP BY d.DepartmentName
             ORDER BY cnt DESC"
        )->fetchAll();
    }

    /**
     * Top 8 counterparties ranked by total contract value.
     */
    public function getTopCounterparties(): array
    {
        return db_query(
            "SELECT cp.CompanyName, COUNT(c.ContractID) AS cnt,
                    SUM(c.ContractValue) AS total_value
             FROM Contract c
             JOIN Counterparty cp ON cp.CounterpartyID = c.CounterpartyID
             WHERE c.ContractValue IS NOT NULL
             GROUP BY cp.CompanyName
             ORDER BY total_value DESC
             LIMIT 8"
        )->fetchAll();
    }

    /**
     * Monthly contract creation counts for the last 12 months.
     */
    public function getMonthlyCreation(): array
    {
        return db_query(
            "SELECT DATE_FORMAT(CreatedAt,'%Y-%m') AS month, COUNT(*) AS cnt
             FROM Contract
             WHERE CreatedAt >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY month
             ORDER BY month ASC"
        )->fetchAll();
    }

    /**
     * Aggregate totals: count, active value, total value, expiring-soon count.
     * Returns a single associative row.
     */
    public function getTotals(): array
    {
        return db_query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN Status='Active' THEN ContractValue ELSE 0 END) AS active_value,
                    SUM(ContractValue) AS total_value,
                    SUM(CASE WHEN Status='Active'
                              AND EndDate BETWEEN CURDATE()
                              AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                         THEN 1 ELSE 0 END) AS expiring_soon
             FROM Contract
             WHERE ContractValue IS NOT NULL"
        )->fetch();
    }

    /**
     * Average review SLA in days (rounded to 1 decimal place).
     * Returns float|string — NULL if no completed reviews exist.
     */
    public function getAverageReviewSla(): mixed
    {
        return db_query(
            "SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, SubmittedAt, ReviewedAt)) / 24, 1) AS sla_days
             FROM Review
             WHERE ReviewedAt IS NOT NULL"
        )->fetchColumn();
    }
}
