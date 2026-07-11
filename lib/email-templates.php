<?php
/**
 * Email HTML templates — Vuka Portal.
 * Each function returns a self-contained HTML string.
 */

require_once __DIR__ . '/../config.php';

function emailShell(string $innerHtml): string {
    return "
    <div style='font-family: Arial, Helvetica, sans-serif; max-width: 560px; margin: 0 auto;
                padding: 24px; color: #212529;'>
      <div style='border-bottom: 3px solid #0F7A45; padding-bottom: 12px; margin-bottom: 20px;'>
        <h2 style='color: #0F7A45; margin: 0;'>Vuka Attachment &amp; Internship Portal</h2>
      </div>
      {$innerHtml}
      <p style='margin-top: 28px; color: #888; font-size: 12px; border-top: 1px solid #eee; padding-top: 12px;'>
        This is an automated message from the Vuka Portal. Please do not reply.
      </p>
    </div>";
}

function emailButton(string $url, string $label): string {
    return "<a href='{$url}' style='display:inline-block;margin-top:16px;padding:11px 22px;
             background:#0F7A45;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;'>
             {$label}</a>";
}

/** Password reset link email. */
function emailPasswordReset(string $resetUrl): string {
    $inner = "
      <p>Hello,</p>
      <p>We received a request to reset the password for your Vuka Portal account.</p>
      " . emailButton($resetUrl, 'Reset My Password') . "
      <p style='margin-top:20px;'>This link expires in <strong>1 hour</strong> and can be used once.</p>
      <p>If you did not request this, you can safely ignore this email.</p>";
    return emailShell($inner);
}

/** Application status-change email. */
function emailStatusChanged(string $studentName, string $status, string $department): string {
    $messages = [
        'accepted'  => "Congratulations! Your application has been <strong>accepted</strong> by the {$department} department.",
        'rejected'  => "We regret to inform you that your application to {$department} was not successful at this time.",
        'deployed'  => "You have been <strong>deployed</strong>. Please report to the {$department} department for your placement.",
        'ongoing'   => "Your attachment/internship with {$department} is now marked as <strong>ongoing</strong>. Welcome aboard!",
        'interview' => "You have been shortlisted for an <strong>interview</strong> with the {$department} department. Check your portal for details.",
        'completed' => "Your attachment/internship with {$department} has been marked <strong>completed</strong>. A performance evaluation is available.",
    ];
    $message = $messages[$status] ?? "Your application status has been updated to <strong>{$status}</strong>.";
    $inner = "
      <p>Dear {$studentName},</p>
      <p>{$message}</p>
      " . emailButton(APP_URL, 'View My Application') . "";
    return emailShell($inner);
}

/** Interview invitation email. */
function emailInterviewScheduled(string $studentName, string $whenText, string $location, string $department): string {
    $inner = "
      <p>Dear {$studentName},</p>
      <p>You have been invited to an interview with the <strong>{$department}</strong> department.</p>
      <table style='margin:16px 0;border-collapse:collapse;'>
        <tr><td style='padding:4px 12px 4px 0;color:#666;'>When:</td><td style='padding:4px 0;'><strong>{$whenText}</strong></td></tr>
        <tr><td style='padding:4px 12px 4px 0;color:#666;'>Where:</td><td style='padding:4px 0;'><strong>{$location}</strong></td></tr>
      </table>
      <p>Please log in to confirm your attendance or flag a conflict.</p>
      " . emailButton(APP_URL, 'Respond to Invitation') . "";
    return emailShell($inner);
}
